<?php

namespace App\Repositories;

use App\Enums\RestockStatus;
use App\Models\Restock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestockRepository
{
    public function getAll(): Collection
    {
        return Restock::with('item.product', 'warehouse', 'request', 'confirm')->get();
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
                'requested_quantity' => $item['qty'],
            ]);
        }

        return $restock;
    }

    public function update(Restock $restock, array $data): Restock
    {
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
                    ['requested_quantity' => $item['qty']]
                );
            }
        }

        return $restock->refresh();
    }

    public function delete(Restock $restock): bool
    {
        return $restock->delete();
    }

    public function confirm(Restock $restock, string $userId): Restock
    {
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

        return $restock;
    }
}
