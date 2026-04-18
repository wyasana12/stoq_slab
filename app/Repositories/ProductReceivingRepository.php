<?php

namespace App\Repositories;

use App\Enums\ReceiveStatus;
use App\Models\ProductReceiving;
use App\Models\ProductReceivingItem;
use InvalidArgumentException;

use function Illuminate\Support\now;

class ProductReceivingRepository
{
    public function getAllPaginated(int $perPage = 10)
    {
        return ProductReceiving::with(['purchase.warehouse:id,name', 'purchase:id,po_code,order_date,warehouse_id', 'user:id,name'])
            ->select('id', 'receiving_code', 'receiving_date', 'status', 'purchase_id', 'receiving_by')
            ->latest()
            ->paginate($perPage);
    }

    public function getById(ProductReceiving $receive): ProductReceiving
    {
        return $receive->load(['purchase.warehouse', 'user', 'items.products']);
    }

    public function updateItems(ProductReceivingItem $item, array $data): ProductReceivingItem
    {
        $item->update([
            'quantity_accepted' => $data['quantity_accepted'],
            'quantity_rejected' => $data['quantity_rejected'],
            'notes' => $data['notes'],
        ]);

        return $item;
    }

    public function updateStatus(ProductReceiving $receive, ReceiveStatus $newStatus, ?string $userId): void
    {
        if (!$receive->status->canTransition($newStatus)) {
            throw new InvalidArgumentException("Status transition from '{$receive->status->value}' to '{$newStatus->value}' is not allowed.");
        }

        $updateData = ['status' => $newStatus];

        if (in_array($newStatus, [ReceiveStatus::FULL, ReceiveStatus::PARTIAL])) {
            if (!$userId) {
                throw new InvalidArgumentException("Receiving user is required.");
            }

            $updateData['receiving_date'] = now();
            $updateData['receiving_by'] = $userId;
        }

        $receive->update($updateData);
    }

    public function softDelete(ProductReceiving $receive): void
    {
        $receive->delete();
    }

    public function getTrashedPaginated(int $perPage = 10)
    {
        return ProductReceiving::onlyTrashed()
            ->with(['purchase:id,po_code,order_date', 'user:id,name', 'purchase.warehouse:id,name'])
            ->latest('deleted_at')
            ->paginate($perPage);
    }

    public function restore(ProductReceiving $receive): void
    {
        $receive->restore();
    }

    public function forceDelete(ProductReceiving $receive): void
    {
        $receive->items()->delete();
        $receive->forceDelete();
    }
}
