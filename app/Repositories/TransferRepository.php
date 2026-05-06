<?php

namespace App\Repositories;

use App\Enums\TransferStatus;
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
            DB::transaction(function () use ($transfer, $data) {
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
            });
        }

        return $transfer->refresh()->load([
            'fromWarehouse:id,name',
            'toWarehouse:id,name',
            'request:id,name',
            'confirm:id,name',
            'item.batch.product',
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
            'item.batch.product',
        ]);

        return [
            'transfer' => $transfer,
            'items' => $transfer->item,
        ];
    }
}
