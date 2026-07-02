<?php

namespace App\Services;

use App\Enums\ReceiveStatus;
use App\Enums\ReturnStatus;
use App\Enums\MutationStatus;
use App\Models\Batch;
use App\Models\ProductReceiving;
use App\Models\StockMutations;
use App\Models\StockReturns;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Enums\PurchaseOrderStatus;
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

            $alreadyReturned = DB::table('stock_returns')
                ->where('receiving_id', $data['receiving_id'])
                ->where('product_id', $data['product_id'])
                ->where('reason', 'mismatch_po')
                ->whereIn('status', [ReturnStatus::REQUESTED->value, ReturnStatus::APPROVED->value])
                ->sum('requested_quantity');

            if (($data['requested_quantity'] + $alreadyReturned) > $receivingItem->quantity_rejected) {
                $sisa = max(0, $receivingItem->quantity_rejected - $alreadyReturned);
                throw new InvalidArgumentException("Jumlah return melebihi sisa kuantitas reject yang dapat direturn ({$sisa} item).");
            }
        } else {
            $totalStock = DB::table('batches')
                ->where('product_id', $data['product_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->sum('current_quantity');

            $alreadyReturned = DB::table('stock_returns')
                ->where('product_id', $data['product_id'])
                ->where('warehouse_id', $data['warehouse_id'])
                ->where('reason', '!=', 'mismatch_po')
                ->where('status', ReturnStatus::REQUESTED->value)
                ->sum('requested_quantity');

            if (($data['requested_quantity'] + $alreadyReturned) > $totalStock) {
                $sisa = max(0, $totalStock - $alreadyReturned);
                throw new InvalidArgumentException("Jumlah return melebihi sisa stok fisik yang tersedia di gudang ({$sisa} item).");
            }
        }

        return DB::transaction(function () use ($data, $userId) {
            $stockReturn = StockReturns::create([
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
                'damage_proof_path' => $data['damage_proof_path'] ?? null,
                'damage_proof_name' => $data['damage_proof_name'] ?? null,
                'damage_proof_mime' => $data['damage_proof_mime'] ?? null,
                'damage_proof_size' => $data['damage_proof_size'] ?? null,
                'damage_proof_uploaded_at' => $data['damage_proof_uploaded_at'] ?? null,
            ]);

            $superAdmins = \App\Models\User::whereHas('roles', function($q) {
                $q->where('name', 'super-admin');
            })->get();
            if ($superAdmins->isNotEmpty()) {
                \Illuminate\Support\Facades\Notification::send(
                    $superAdmins,
                    new \App\Notifications\ReturnNotification(
                        $stockReturn,
                        'Pengajuan Retur Baru',
                        "Terdapat pengajuan retur baru yang membutuhkan persetujuan."
                    )
                );
            }

            return $stockReturn;
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

                if ($stockReturns->reason !== 'mismatch_po') {
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
                }

                $stockReturns->update([
                    'status' => $newStatus->value,
                    'approved_quantity' => $approvedQuantity,
                    'confirmed_by' => $userId,
                    'notes' => $data['notes'] ?? $stockReturns->notes,
                ]);

                // Create replacement PO automatically
                $receiving = ProductReceiving::find($stockReturns->receiving_id);
                if ($receiving && $receiving->receivable_type === 'purchase_order' && $receiving->receivable_id) {
                    $originalPo = PurchaseOrder::find($receiving->receivable_id);
                    if ($originalPo) {
                        $warehouse = DB::table('warehouses')->where('id', $stockReturns->warehouse_id)->first();
                        $warehouseCode = $warehouse ? strtoupper($warehouse->warehouse_code) : 'WHS';
                        $poCode = 'PO-RET-' . $warehouseCode . '-' . strtoupper(Str::random(6));

                        $originalPoItem = PurchaseOrderItem::where('purchase_id', $originalPo->id)
                            ->where('product_id', $stockReturns->product_id)
                            ->first();

                        $unitPrice = $originalPoItem ? $originalPoItem->unit_price : 0;
                        $totalAmount = $approvedQuantity * $unitPrice;

                        $newPo = PurchaseOrder::create([
                            'po_code' => $poCode,
                            'created_by' => $userId,
                            'supplier_id' => $originalPo->supplier_id,
                            'warehouse_id' => $stockReturns->warehouse_id,
                            'order_date' => now(),
                            'total_amount' => $totalAmount,
                            'status' => PurchaseOrderStatus::ORDERED->value,
                            'notes' => 'PO Pengganti otomatis dari Return: ' . $stockReturns->return_code,
                        ]);

                        PurchaseOrderItem::create([
                            'purchase_id' => $newPo->id,
                            'product_id' => $stockReturns->product_id,
                            'quantity_ordered' => $approvedQuantity,
                            'quantity_approved' => $approvedQuantity,
                            'unit_price' => $unitPrice,
                            'subtotal' => $totalAmount,
                        ]);
                    }
                }
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

            $alreadyReturned = DB::table('stock_returns')
                ->where('receiving_id', $stockReturns->receiving_id)
                ->where('product_id', $stockReturns->product_id)
                ->where('reason', 'mismatch_po')
                ->whereIn('status', [ReturnStatus::REQUESTED->value, ReturnStatus::APPROVED->value])
                ->where('id', '!=', $stockReturns->id)
                ->sum('requested_quantity');

            if (($quantityToCheck + $alreadyReturned) > $receivingItem->quantity_rejected) {
                $sisa = max(0, $receivingItem->quantity_rejected - $alreadyReturned);
                throw new InvalidArgumentException("Jumlah return melebihi sisa kuantitas reject yang dapat direturn ({$sisa} item).");
            }
        } else {
            $totalStock = DB::table('batches')
                ->where('product_id', $stockReturns->product_id)
                ->where('warehouse_id', $stockReturns->warehouse_id)
                ->sum('current_quantity');

            $alreadyReturned = DB::table('stock_returns')
                ->where('product_id', $stockReturns->product_id)
                ->where('warehouse_id', $stockReturns->warehouse_id)
                ->where('reason', '!=', 'mismatch_po')
                ->where('status', ReturnStatus::REQUESTED->value)
                ->where('id', '!=', $stockReturns->id)
                ->sum('requested_quantity');

            if (($quantityToCheck + $alreadyReturned) > $totalStock) {
                $sisa = max(0, $totalStock - $alreadyReturned);
                throw new InvalidArgumentException("Jumlah return melebihi sisa stok fisik yang tersedia di gudang ({$sisa} item).");
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
