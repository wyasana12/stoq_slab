<?php

namespace App\Repositories;

use App\Enums\MutationStatus;
use App\Enums\TransferStatus;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Models\StockTransfers;
use App\Services\BatchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class TransferRepository
{
    public function __construct(protected BatchService $batchService) {}

    public function getAll(?string $transferType = null): Collection
    {
        $query = StockTransfers::with([
            'fromWarehouse:id,name',
            'toWarehouse:id,name',
            'request:id,name',
            'confirm:id,name',
            'products',
        ]);

        if ($transferType) {
            if ($transferType === 'in_request') {
                $query->where('transfer_type', 'in');
            } elseif ($transferType !== 'incoming') {
                $query->where('transfer_type', $transferType);
            }
        }

        if ($warehouseId = Auth::user()?->warehouse_id) {
            if ($transferType === 'in') {
                $query->where('to_warehouse_id', $warehouseId);
            } elseif ($transferType === 'out') {
                $query->where('from_warehouse_id', $warehouseId);
            } elseif ($transferType === 'in_request') {
                $query->where('from_warehouse_id', $warehouseId);
            } elseif ($transferType === 'incoming') {
                $query->where('to_warehouse_id', $warehouseId);
            } else {
                // default behaviour: scope to user's from_warehouse
                $query->where('from_warehouse_id', $warehouseId);
            }
        }

        return $query->get();
    }

    public function create(array $data): StockTransfers
    {
        $userId = Auth::user()?->id;
        $warehouseId = Auth::user()?->warehouse_id;

        if ($data['transfer_type'] === 'in') {
            $data['to_warehouse_id'] = $warehouseId;
        } elseif ($data['transfer_type'] === 'out') {
            $data['from_warehouse_id'] = $warehouseId;
        }

        if ($userId) {
            $data['requested_by'] = $userId;
        }

        $transfer = StockTransfers::create([
            'transfer_code' => $data['transfer_code'] ?? 'TRF-' . now()->format('Ymd') . '-' . rand(1000, 9999),
            'transfer_type' => $data['transfer_type'],
            'from_warehouse_id' => $data['from_warehouse_id'] ?? null,
            'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
            'product_id' => $data['product_id'],
            'requested_quantity' => $data['requested_quantity'],
            'approved_quantity' => $data['approved_quantity'] ?? 0,
            'requested_by' => $data['requested_by'],
            'confirmed_by' => $data['confirmed_by'] ?? null,
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? TransferStatus::DRAFT->value,
        ]);

        if (!empty($data['items'])) {
            $transfer->item()->createMany(collect($data['items'])->map(function ($item) {
                return [
                    'id' => (string) Str::ulid(),
                    'batch_id' => $item['batch_id'],
                    'requested_quantity' => $item['requested_quantity'],
                    'approved_quantity' => $item['approved_quantity'] ?? $item['requested_quantity'],
                ];
            })->all());
        }

        return $transfer->refresh()->load([
            'fromWarehouse:id,name',
            'toWarehouse:id,name',
            'request:id,name',
            'confirm:id,name',
            'products',
        ]);
    }

    public function update(StockTransfers $transfer, array $data): StockTransfers
    {
        return DB::transaction(function () use ($transfer, $data) {
            $auth = Auth::user()?->id;
            if ($auth) {
                $data['confirmed_by'] = $auth;
            }

            $currentStatus = $transfer->status instanceof TransferStatus
                ? $transfer->status
                : TransferStatus::tryFrom($transfer->status);
            $newStatus = isset($data['status'])
                ? TransferStatus::tryFrom($data['status'])
                : null;

            $transfer->update([
                'from_warehouse_id' => $data['from_warehouse_id'] ?? $transfer->from_warehouse_id,
                'to_warehouse_id' => $data['to_warehouse_id'] ?? $transfer->to_warehouse_id,
                'approved_quantity' => $data['approved_quantity'] ?? $transfer->approved_quantity,
                'confirmed_by' => $data['confirmed_by'],
                'reason' => array_key_exists('reason', $data) ? $data['reason'] : $transfer->reason,
                'notes' => $data['notes'] ?? $transfer->notes,
                'status' => $data['status'],
            ]);

            $transfer->refresh();

            if ($newStatus === TransferStatus::ON_DELIVERY && $currentStatus !== TransferStatus::ON_DELIVERY) {
                $this->deductSourceStock($transfer);
                $transfer->refresh();
            }

            return $transfer->load([
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
                'request:id,name',
                'confirm:id,name',
                'products',
            ]);
        });
    }

    private function deductSourceStock(StockTransfers $transfer): void
    {
        $transfer->loadMissing('products', 'fromWarehouse');

        if (! $transfer->from_warehouse_id) {
            throw new \InvalidArgumentException('Gudang asal belum ditentukan untuk transfer ini.');
        }

        $product = $transfer->products;

        if (! $product) {
            throw new \InvalidArgumentException('Produk transfer tidak ditemukan.');
        }

        $quantity = $transfer->approved_quantity > 0 ? $transfer->approved_quantity : $transfer->requested_quantity;

        $existingMutation = StockMutations::where('reference_type', 'TRANSFER')
            ->where('reference_id', $transfer->id)
            ->where('warehouse_id', $transfer->from_warehouse_id)
            ->exists();
            
        if ($existingMutation) {
            return;
        }

        $sourceBatch = Batch::query()
            ->where('warehouse_id', $transfer->from_warehouse_id)
            ->where('product_id', $transfer->product_id)
            ->where('current_quantity', '>=', $quantity)
            ->orderBy('expired_date')
            ->first();

        if (! $sourceBatch) {
            throw new \InvalidArgumentException('Batch sumber dengan stok mencukupi tidak ditemukan.');
        }

        $sourceBefore = $sourceBatch->current_quantity;
        $updated = Batch::query()
            ->whereKey($sourceBatch->id)
            ->where('current_quantity', '>=', $quantity)
            ->update(['current_quantity' => DB::raw('current_quantity - ' . (int) $quantity)]);

        if ($updated !== 1) {
            throw new \InvalidArgumentException('Gagal memotong stok dari batch sumber.');
        }

        $sourceBatch->refresh();

        $this->batchService->releaseLocation($sourceBatch, $quantity);

        StockMutations::create([
            'warehouse_id' => $sourceBatch->warehouse_id,
            'batch_id' => $sourceBatch->id,
            'change_quantity' => $quantity,
            'before_quantity' => $sourceBefore,
            'after_quantity' => $sourceBatch->current_quantity,
            'reference_type' => 'TRANSFER',
            'reference_id' => $transfer->id,
            'notes' => 'Kirim transfer ke ' . ($transfer->toWarehouse->name ?? 'Gudang Tujuan'),
            'status' => MutationStatus::TRANSFER_COMPLETED->value,
        ]);
    }


    public function delete(StockTransfers $transfer): bool
    {
        return $transfer->delete();
    }

    public function show(StockTransfers $transfer): array
    {
        $transfer->load([
            'fromWarehouse:id,name',
            'toWarehouse:id,name',
            'request:id,name',
            'confirm:id,name',
            'products',
        ]);

        return [
            'transfer' => $transfer,
            'items' => [[
                'product_id' => $transfer->product_id,
                'product' => $transfer->products,
                'requested_quantity' => $transfer->requested_quantity,
                'approved_quantity' => $transfer->approved_quantity,
            ]],
        ];
    }
}
