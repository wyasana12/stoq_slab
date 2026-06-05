<?php

namespace App\Services;

use App\Enums\ReceiveStatus;
use App\Enums\ReturnStatus;
use App\Enums\MutationStatus;
use App\Models\Batch;
use App\Models\ProductReceiving;
use App\Models\StockMutations;
use App\Models\StockReturns;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;
use InvalidArgumentException;

class ReturnService
{
    public function storeReturn(array $data, string $userId): StockReturns
    {
        $receiving = ProductReceiving::findOrFail($data['receiving_id']);

        if ($receiving->status === ReceiveStatus::PROCESS->value) {
            throw new InvalidArgumentException('Return hanya dapat diajukan untuk receiving yang sudah selesai diproses.');
        }

        $receivingDate = $receiving->receiving_date
            ? Carbon::parse($receiving->receiving_date)
            : $receiving->created_at;

        if (now()->diffInDays($receivingDate) > 3) {
            throw new InvalidArgumentException('Pengajuan return ditolak. Batas waktu maksimal adalah 3 hari sejak barang diterima.');
        }

        $receivingItem = DB::table('product_receiving_items')
            ->where('receiving_id', $data['receiving_id'])
            ->where('product_id', $data['product_id'])
            ->first();

        if (! $receivingItem) {
            throw new InvalidArgumentException('Produk tidak dapat diajukan return karena tidak terdaftar di receiving ini.');
        }

        if (empty($data['warehouse_id'])) {
            throw new InvalidArgumentException('Warehouse return tidak tersedia untuk user saat ini.');
        }

        if ($data['reason'] === 'mismatch_po') {
            if ($receivingItem->quantity_rejected <= 0) {
                throw new InvalidArgumentException('Tidak ada item yang di-reject pada receiving ini.');
            }

            if ($data['requested_quantity'] > $receivingItem->quantity_rejected) {
                throw new InvalidArgumentException('Jumlah return melebihi kuantitas produk yang di-reject.');
            }
        } else {
            if ($data['requested_quantity'] > $receivingItem->quantity_accepted) {
                throw new InvalidArgumentException('Jumlah return melebihi kuantitas produk yang diterima.');
            }
        }

        return DB::transaction(function () use ($data, $userId) {
            return StockReturns::create([
                'return_code' => 'RT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
                'receiving_id' => $data['receiving_id'],
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'requested_quantity' => $data['requested_quantity'],
                'approved_quantity' => 0,
                'reason' => $data['reason'],
                'requested_by' => $userId,
                'notes' => $data['notes'] ?? null,
                'status' => ReturnStatus::REQUESTED->value,
            ]);
        });
    }

    public function confirmReturn(StockReturns $stockReturns, array $data, string $userId): StockReturns
    {
        $currentStatus = ReturnStatus::from($stockReturns->status);
        $newStatus = ReturnStatus::from($data['status']);

        if (! $currentStatus->canTransition($newStatus)) {
            throw new InvalidArgumentException("Transisi status dari {$currentStatus->value} ke {$newStatus->value} tidak diizinkan.");
        }

        if ($newStatus === ReturnStatus::APPROVED && empty($data['approved_quantity'])) {
            throw new InvalidArgumentException('approved_quantity harus diisi saat approve.');
        }

        return DB::transaction(function () use ($stockReturns, $data, $newStatus, $userId) {
            if ($newStatus === ReturnStatus::APPROVED) {
                $approvedQuantity = (int) $data['approved_quantity'];

                $batches = Batch::query()
                    ->where('product_id', $stockReturns->product_id)
                    ->where('warehouse_id', $stockReturns->warehouse_id)
                    ->where('current_quantity', '>', 0)
                    ->orderBy('created_at')
                    ->lockForUpdate()
                    ->get();

                $availableQuantity = $batches->sum('current_quantity');
                if ($availableQuantity < $approvedQuantity) {
                    throw new InvalidArgumentException('Stok tidak mencukupi untuk return ini.');
                }

                $remaining = $approvedQuantity;
                foreach ($batches as $batch) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $decrement = min($batch->current_quantity, $remaining);
                    $before = $batch->current_quantity;
                    $batch->decrement('current_quantity', $decrement);
                    $batch->refresh();

                    StockMutations::record(
                        $batch->warehouse_id,
                        $batch->id,
                        $before,
                        -$decrement,
                        MutationStatus::RETURN_COMPLETED,
                        'RETURN',
                        $stockReturns->id,
                        'Return approved ' . $stockReturns->return_code
                    );

                    $remaining -= $decrement;
                }

                $stockReturns->update([
                    'status' => $newStatus->value,
                    'approved_quantity' => $approvedQuantity,
                    'confirmed_by' => $userId,
                    'notes' => $data['notes'] ?? $stockReturns->notes,
                ]);
            } else {
                $stockReturns->update([
                    'status' => $newStatus->value,
                    'approved_quantity' => 0,
                    'confirmed_by' => $userId,
                    'notes' => $data['notes'] ?? $stockReturns->notes,
                ]);
            }

            return $stockReturns->refresh();
        });
    }

    public function updateReturn(StockReturns $stockReturns, array $data): StockReturns
    {
        if ($stockReturns->status !== ReturnStatus::REQUESTED->value) {
            throw new InvalidArgumentException('Data pengajuan return tidak dapat diubah karena sudah diproses.');
        }

        $receiving = ProductReceiving::findOrFail($stockReturns->receiving_id);

        if ($receiving->status === ReceiveStatus::PROCESS->value) {
            throw new InvalidArgumentException('Gagal memperbarui. Return hanya dapat diajukan untuk receiving yang sudah selesai diproses.');
        }

        $receivingDate = $receiving->receiving_date
            ? Carbon::parse($receiving->receiving_date)
            : $receiving->created_at;

        if (now()->diffInDays($receivingDate) > 3) {
            throw new InvalidArgumentException('Gagal memperbarui. Batas waktu pengajuan maksimal adalah 3 hari sejak barang diterima.');
        }

        $receivingItem = DB::table('product_receiving_items')
            ->where('receiving_id', $stockReturns->receiving_id)
            ->where('product_id', $stockReturns->product_id)
            ->first();

        if (! $receivingItem) {
            throw new InvalidArgumentException('Produk tidak dapat diupdate karena tidak terdaftar di receiving ini.');
        }

        $quantityToCheck = $data['requested_quantity'] ?? $stockReturns->requested_quantity;
        $reasonToCheck = $data['reason'] ?? $stockReturns->reason;

        if ($reasonToCheck === 'mismatch_po') {
            if ($receivingItem->quantity_rejected <= 0) {
                throw new InvalidArgumentException('Tidak ada item yang di-reject pada receiving ini.');
            }

            if ($quantityToCheck > $receivingItem->quantity_rejected) {
                throw new InvalidArgumentException('Jumlah return melebihi kuantitas produk yang di-reject.');
            }
        } else {
            if ($quantityToCheck > $receivingItem->quantity_accepted) {
                throw new InvalidArgumentException('Jumlah return melebihi kuantitas produk yang diterima.');
            }
        }

        return DB::transaction(function () use ($stockReturns, $data) {
            $stockReturns->update($data);
            return $stockReturns->refresh();
        });
    }

    public function deleteReturn(StockReturns $stockReturns): bool
    {
        if ($stockReturns->status !== ReturnStatus::REQUESTED->value) {
            throw new InvalidArgumentException('Data pengajuan return tidak dapat dihapus karena sudah diproses.');
        }

        return DB::transaction(function () use ($stockReturns) {
            return (bool) $stockReturns->delete();
        });
    }
}
