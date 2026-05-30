<?php

namespace App\Repositories;

use App\Enums\MutationStatus;
use App\Enums\RestockStatus;
use App\Models\Batch;
use App\Models\Restock;
use App\Models\StockMutations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestockRepository
{
    public function getAll(): Collection
    {
        $query = Restock::with('item.product', 'warehouse', 'request', 'confirm');

        if ($warehouseId = Auth::user()?->warehouse_id) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }

    public function create(array $data): Restock
    {
        $restock = Restock::create([
            'restock_code' => 'RC-' . now()->format('Ymd') . '-' . rand(1, 9999),
            'warehouse_id' => $data['warehouse_id'],
            'requested_by' => $data['requested_by'],
            'status'       => isset($data['status'])
                ? RestockStatus::fromValue($data['status'])->value
                : RestockStatus::REQUESTED->value,
            'notes'        => $data['notes'] ?? null,
        ]);

        foreach ($data['products'] as $item) {
            $restock->item()->create([
                'id' => (string) Str::ulid(),
                'product_id' => $item['id'],
                'requested_quantity' => $item['approved_quantity'] ?? $item['quantity_requested'] ?? $item['requested_quantity'] ?? $item['qty'] ?? 0,
            ]);
        }

        return $restock;
    }

    public function update(Restock $restock, array $data): Restock
    {
        return DB::transaction(function () use ($restock, $data) {
            if (isset($data['status'])) {
                $currentStatus = RestockStatus::fromValue($restock->status instanceof RestockStatus ? $restock->status->value : $restock->status);
                $newStatus = RestockStatus::fromValue($data['status']);

                if (! $currentStatus->canTransition($newStatus)) {
                    throw new \InvalidArgumentException(
                        "Cannot update restock status from {$currentStatus->value} to {$newStatus->value}."
                    );
                }

                $data['status'] = $newStatus->value;
            }

            $restock->update($data);

            if (isset($data['products'])) {
                foreach ($data['products'] as $item) {
                    $restock->item()->updateOrCreate(
                        ['product_id' => $item['id']],
                        ['requested_quantity' => $item['approved_quantity'] ?? $item['quantity_requested'] ?? $item['requested_quantity'] ?? $item['qty'] ?? 0]
                    );
                }
            }

            if (isset($data['status']) && $data['status'] === RestockStatus::RESTOCKED->value) {
                $this->applyStockMutation($restock->refresh());
            }

            return $restock->refresh();
        });
    }

    public function delete(Restock $restock): bool
    {
        return $restock->delete();
    }

    private function applyStockMutation(Restock $restock): void
    {
        $restock->loadMissing('item');

        foreach ($restock->item as $item) {
            $batch = Batch::query()
                ->lockForUpdate()
                ->where('product_id', $item->product_id)
                ->where('warehouse_id', $restock->warehouse_id)
                ->orderBy('expired_date')
                ->first();

            if (! $batch) {
                throw new \InvalidArgumentException("Batch not found for product {$item->product_id} in warehouse {$restock->warehouse_id}.");
            }

            $before = $batch->current_quantity;
            $batch->increment('current_quantity', $item->requested_quantity);
            $batch->refresh();

            StockMutations::create([
                'warehouse_id' => $batch->warehouse_id,
                'batch_id' => $batch->id,
                'change_quantity' => $item->requested_quantity,
                'before_quantity' => $before,
                'after_quantity' => $batch->current_quantity,
                'reference_type' => 'RESTOCK',
                'reference_id' => $restock->id,
                'notes' => 'Restock completed: ' . $restock->restock_code,
                'status' => MutationStatus::RESTOCK_COMPLETED->value,
            ]);
        }
    }

    public function confirm(Restock $restock, string $userId): Restock
    {
        return DB::transaction(function () use ($restock, $userId) {
            $currentStatus = RestockStatus::fromValue($restock->status instanceof RestockStatus ? $restock->status->value : $restock->status);
            $nextStatus = RestockStatus::RESTOCKED;

            if (! $currentStatus->canTransition($nextStatus)) {
                throw new \InvalidArgumentException(
                    "Cannot confirm restock because status {$currentStatus->value} cannot transition to {$nextStatus->value}."
                );
            }

            $restock->update([
                'confirmed_by' => $userId,
                'status' => $nextStatus->value,
            ]);

            $this->applyStockMutation($restock->refresh());

            return $restock;
        });
    }
}
