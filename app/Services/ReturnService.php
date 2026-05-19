<?php

namespace App\Services;

use App\Enums\ReceiveStatus;
use App\Enums\ReturnStatus;
use App\Models\ProductReceiving;
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

        if ($receiving->status !== ReceiveStatus::REJECT->value) {
            throw new InvalidArgumentException('Return hanya dapat diajukan untuk receiving yang berstatus rejected.');
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
            $stockReturns->update([
                'status' => $newStatus->value,
                'approved_quantity' => $data['approved_quantity'] ?? $stockReturns->approved_quantity,
                'confirmed_by' => $userId,
                'notes' => $data['notes'] ?? $stockReturns->notes,
            ]);

            return $stockReturns->refresh();
        });
    }

    public function updateReturn(StockReturns $stockReturns, array $data): StockReturns
    {
        if ($stockReturns->status !== ReturnStatus::REQUESTED->value) {
            throw new InvalidArgumentException('Data pengajuan return tidak dapat diubah karena sudah diproses.');
        }

        $receiving = ProductReceiving::findOrFail($stockReturns->receiving_id);

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
