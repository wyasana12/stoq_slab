<?php

namespace App\Repositories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PurchaseOrderRepository
{
    public function getAllPaginated(int $perPage = 10, string $currentUserId)
    {
        return PurchaseOrder::with(['warehouse:id,name', 'user:id,name'])
            ->select(['id', 'po_code', 'total_amount', 'status', 'order_date', 'approved_at', 'created_at', 'updated_at', 'warehouse_id', 'created_by'])
            ->where(function ($q) use ($currentUserId) {
                $q->where('status', '!=', PurchaseOrderStatus::DRAFT)
                    ->orWhere(function ($qu) use ($currentUserId) {
                        $qu->where('status', PurchaseOrderStatus::DRAFT)
                            ->where('created_by', $currentUserId);
                    });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function createRequest(array $data): PurchaseOrder
    {
        return PurchaseOrder::create($data);
    }

    public function assignProducts(PurchaseOrder $purchase, array $products): void
    {
        $now = now();
        $insertData = [];

        foreach ($products as $i) {
            $insertData[] = [
                'id' => (string) Str::ulid(),
                'purchase_id' => $purchase->id,
                'product_id' => $i['product_id'],
                'quantity_ordered' => $i['quantity_ordered'],
                'unit_price' => $i['unit_price'],
                'subtotal' => $i['subtotal'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        PurchaseOrderItem::insert($insertData);
    }

    public function updateStatus(PurchaseOrder $purchase, PurchaseOrderStatus $newStatus): void
    {
        if (!$purchase->status->canTransition($newStatus)) {
            throw new InvalidArgumentException("Status transition from '{$purchase->status->value}' to '{$newStatus->value}' is not allowed.");
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus == PurchaseOrderStatus::APPROVED) {
            $updateData['approved_at'] = now();
        }

        if ($newStatus == PurchaseOrderStatus::ORDERED) {
            $updateData['order_date'] = now();
        }

        $purchase->update($updateData);
    }

    public function updateRequest(PurchaseOrder $purchase, array $data)
    {
        return $purchase->update($data);
    }

    public function deleteItems(PurchaseOrder $purchase): void
    {
        $purchase->items()->delete();
    }

    public function getById(PurchaseOrder $purchase): PurchaseOrder
    {
        return $purchase->load(['warehouse', 'supplier', 'items', 'user']);
    }

    public function softDelete(PurchaseOrder $purchase): void
    {
        $purchase->delete();
    }

    public function getTrashedPaginated(int $perPage = 10, string $userId)
    {
        return PurchaseOrder::onlyTrashed()
                        ->with(['warehouse:id,name', 'user:id,name', 'supplier:id,name', 'items'])
                        ->where('created_by', $userId)
                        ->latest('deleted_at')
                        ->paginate($perPage);    
    }

    public function restore(PurchaseOrder $purchase): void
    {
        $purchase->restore();
    }

    public function forceDelete(PurchaseOrder $purchase): void
    {
        $purchase->items()->delete();
        $purchase->forceDelete();
    }
}
