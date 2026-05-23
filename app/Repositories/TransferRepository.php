<?php

namespace App\Repositories;

use App\Enums\MutationStatus;
use App\Enums\TransferStatus;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Models\StockTransfers;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransferRepository
{
    public function getAll(): Collection
    {
        $query = StockTransfers::with([
            'fromWarehouse:id,name',
            'toWarehouse:id,name',
            'request:id,name',
            'confirm:id,name',
            'item.batch.product',
        ]);

        if ($warehouseId = Auth::user()?->warehouse_id) {
            $query->where('from_warehouse_id', $warehouseId);
        }

        return $query->get();
    }

    public function create(array $data): StockTransfers
    {
        $transfer = StockTransfers::create([
            'transfer_code' => $data['transfer_code'] ?? 'TRF-' . now()->format('Ymd') . '-' . rand(1000, 9999),
            'from_warehouse_id' => $data['from_warehouse_id'],
            'to_warehouse_id' => $data['to_warehouse_id'],
            'requested_by' => $data['requested_by'],
            'confirmed_by' => $data['confirmed_by'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? TransferStatus::DRAFT,
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
            'item.batch.product',
        ]);
    }

    public function update(StockTransfers $transfer, array $data): StockTransfers
    {
        return DB::transaction(function () use ($transfer, $data) {
            if (isset($data['status'])) {
                $currentStatus = $transfer->status instanceof TransferStatus
                    ? $transfer->status
                    : TransferStatus::from($transfer->status);

                $newStatus = TransferStatus::from($data['status']);

                if (! $currentStatus->canTransition($newStatus)) {
                    throw new \InvalidArgumentException(
                        "Cannot update transfer status from {$currentStatus->value} to {$newStatus->value}."
                    );
                }

                $data['status'] = $newStatus;
            }

            $transfer->update(array_filter([
                'transfer_code' => $data['transfer_code'] ?? null,
                'from_warehouse_id' => $data['from_warehouse_id'] ?? null,
                'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
                'confirmed_by' => $data['confirmed_by'] ?? $transfer->confirmed_by,
                'notes' => $data['notes'] ?? $transfer->notes,
                'status' => $data['status'] ?? null,
            ], fn($value) => $value !== null));

            if (array_key_exists('items', $data)) {
                $transfer->item()->delete();

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
            }

            if (isset($data['status']) && $data['status'] === TransferStatus::COMPLETED) {
                $transfer = $transfer->refresh();
                $this->applyStockMutation($transfer);
            }

            return $transfer->refresh()->load([
                'fromWarehouse:id,name',
                'toWarehouse:id,name',
                'request:id,name',
                'confirm:id,name',
                'item.batch.product',
            ]);
        });
    }

    private function applyStockMutation(StockTransfers $transfer): void
    {
        $transfer->loadMissing('item.batch', 'fromWarehouse', 'toWarehouse');

        foreach ($transfer->item as $item) {
            $batch = $item->batch;

            if (! $batch) {
                throw new \InvalidArgumentException('Batch tidak ditemukan untuk item transfer.');
            }

            $quantity = $item->approved_quantity > 0 ? $item->approved_quantity : $item->requested_quantity;

            if ($batch->current_quantity < $quantity) {
                throw new \InvalidArgumentException('Stok batch tidak cukup untuk menyelesaikan transfer.');
            }

            $sourceBefore = $batch->current_quantity;
            $batch->decrement('current_quantity', $quantity);

            StockMutations::create([
                'warehouse_id' => $batch->warehouse_id,
                'batch_id' => $batch->id,
                'change_quantity' => $quantity,
                'before_quantity' => $sourceBefore,
                'after_quantity' => $batch->current_quantity,
                'reference_type' => 'TRANSFER',
                'reference_id' => $transfer->id,
                'notes' => 'Kirim transfer ke ' . $transfer->toWarehouse->name,
                'status' => MutationStatus::TRASFER_COMPLETED->value,
            ]);

            $destinationBatch = Batch::query()
                ->where('warehouse_id', $transfer->to_warehouse_id)
                ->where('product_id', $batch->product_id)
                ->where('production_date', $batch->production_date)
                ->where('expired_date', $batch->expired_date)
                ->first();

            if (! $destinationBatch) {
                $destinationBatch = Batch::create([
                    'batch_code' => 'BTCH-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                    'product_id' => $batch->product_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'rack_location' => $batch->rack_location,
                    'production_date' => $batch->production_date,
                    'expired_date' => $batch->expired_date,
                    'initial_quantity' => $quantity,
                    'current_quantity' => $quantity,
                    'price' => $batch->price,
                    'condition' => $batch->condition,
                    'barcode' => $batch->barcode,
                ]);

                $destinationBefore = 0;
            } else {
                $destinationBefore = $destinationBatch->current_quantity;
                $destinationBatch->increment('current_quantity', $quantity);
            }

            StockMutations::create([
                'warehouse_id' => $transfer->to_warehouse_id,
                'batch_id' => $destinationBatch->id,
                'change_quantity' => $quantity,
                'before_quantity' => $destinationBefore,
                'after_quantity' => $destinationBatch->current_quantity,
                'reference_type' => 'TRANSFER',
                'reference_id' => $transfer->id,
                'notes' => 'Terima transfer dari ' . $transfer->fromWarehouse->name,
                'status' => MutationStatus::TRASFER_COMPLETED->value,
            ]);
        }
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
            'item.batch.product',
        ]);

        return [
            'transfer' => $transfer,
            'items' => $transfer->item,
        ];
    }
}
