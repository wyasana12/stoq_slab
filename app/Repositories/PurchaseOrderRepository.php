<?php

namespace App\Repositories;

use App\Enums\PurchaseOrderStatus;
use App\Models\ProductSupplierItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PurchaseOrderRepository
{
    public function getSummary()
    {
        $warehouseId = Auth::user()->warehouse_id;

        $query = PurchaseOrder::query()
            ->where('warehouse_id', $warehouseId);

        return [
            'total_po' => (clone $query)->count(),
            'approved' => (clone $query)
                ->where('status', PurchaseOrderStatus::APPROVED)
                ->count(),
            'submitted_draft' => (clone $query)
                ->whereIn('status', [
                    PurchaseOrderStatus::SUBMITTED,
                    PurchaseOrderStatus::DRAFT,
                ])
                ->count(),
            'ordered_closed' => (clone $query)
                ->whereIn('status', [
                    PurchaseOrderStatus::ORDERED,
                    PurchaseOrderStatus::CLOSED,
                ])
                ->count(),
            'total_amount' => (clone $query)->sum('total_amount'),
        ];
    }
    public function getAllPaginated()
    {
        $user = Auth::user();

        $purchases = PurchaseOrder::with(['supplier:id,name', 'user:id,name'])
            ->select(['id', 'po_code', 'total_amount', 'status', 'approved_at', 'order_date', 'expected_date', 'supplier_id', 'created_by'])
            ->where('warehouse_id', $user->warehouse_id)
            ->where(function ($q) use ($user) {
                $q->where('status', '!=', PurchaseOrderStatus::DRAFT)
                    ->orWhere(function ($draft) use ($user) {
                        $draft->where('status', PurchaseOrderStatus::DRAFT)
                            ->where('created_by', $user->id);
                    });
            })
            ->latest()
            ->get();

        return $purchases;
    }

    public function getAllConfirmation()
    {
        $purchases = PurchaseOrder::with(['warehouse:id,name', 'user:id,name', 'supplier:id,name'])
            ->select(['id', 'po_code', 'total_amount', 'status', 'order_date', 'approved_at', 'created_at', 'warehouse_id', 'created_by', 'supplier_id'])
            ->where('status', '!=', 'draft')
            ->latest()
            ->get();
        return $purchases;
    }

    public function createRequest(array $data): PurchaseOrder
    {
        return PurchaseOrder::create($data);
    }

    public function assignProducts(PurchaseOrder $purchase, array $products): void
    {
        if (empty($products)) {
            return;
        }

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

    public function updateStatus(PurchaseOrder $purchase, PurchaseOrderStatus $newStatus, array $data): void
    {
        if (!$purchase->status->canTransition($newStatus)) {
            throw new InvalidArgumentException("Status transition from '{$purchase->status->value}' to '{$newStatus->value}' is not allowed.");
        }

        $updateData = ['status' => $newStatus];

        if ($newStatus === PurchaseOrderStatus::APPROVED) {
            $updateData['approved_at'] = now();
        }

        if ($newStatus === PurchaseOrderStatus::ORDERED) {
            $updateData['order_date'] = now();
            $updateData['expected_date'] = $this->applyExpectedDates($purchase);
        }

        $purchase->update($updateData);

        if ($newStatus === PurchaseOrderStatus::APPROVED) {
            $this->applyApproval($purchase, $data);
        }
    }

    public function updateRequest(PurchaseOrder $purchase, array $data)
    {
        return $purchase->update($data);
    }

    public function syncProducts(PurchaseOrder $purchase, array $products): void
    {
        $incomingIds = collect($products)->pluck('product_id')->filter()->toArray();

        if (!empty($incomingIds)) {
            $purchase->items()
                ->whereNotIn('product_id', $incomingIds)
                ->forceDelete();
        }

        foreach ($products as $p) {
            $purchase->items()->updateOrCreate(
                [
                    'product_id' => $p['product_id'],
                ],
                [
                    'quantity_ordered' => $p['quantity_ordered'],
                    'unit_price' => $p['unit_price'],
                    'subtotal' => $p['subtotal'],
                ]
            );
        }
    }

    public function getById(PurchaseOrder $purchase): PurchaseOrder
    {
        $purchase->load(['warehouse', 'supplier', 'items.product', 'user']);

        foreach ($purchase->items as $item) {
            $moq = DB::table('product_supplier_items')
                ->where('supplier_id', $purchase->supplier_id)
                ->where('product_id', $item->product_id)
                ->first(['min_order_quantity', 'lead_time_days']);

            if ($item->product) {
                $item->product->min_order_quantity = $moq?->min_order_quantity ?? 1;
                $item->product->lead_time_days = isset($moq->lead_time_days) ? (int)$moq->lead_time_days : 0;
            }
        }

        return $purchase;
    }

    public function softDelete(PurchaseOrder $purchase): void
    {
        $purchase->delete();
    }

    public function getTrashedPaginated()
    {
        $userId = Auth::id();

        $purchases = PurchaseOrder::onlyTrashed()
            ->with(['warehouse:id,name', 'user:id,name', 'supplier:id,name', 'items'])
            ->where('created_by', $userId)
            ->latest('deleted_at')
            ->get();

        return $purchases;
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

    protected function applyApproval(PurchaseOrder $purchase, array $data): void
    {
        if (!empty($data['rejected_item_ids'])) {
            PurchaseOrderItem::where('purchase_id', $purchase->id)
                ->whereIn('id', $data['rejected_item_ids'])
                ->delete();
        }

        foreach ($data['items'] ?? [] as $itemInput) {
            $item = PurchaseOrderItem::where('id', $itemInput['id'])
                ->where('purchase_id', $purchase->id)
                ->first();

            if ($item) {
                if ($itemInput['quantity_approved'] > $item->quantity_ordered) {
                    throw new \InvalidArgumentException("Quantity approved tidak boleh lebih dari quantity ordered.");
                }

                $newSubtotal = $itemInput['quantity_approved'] * $item->unit_price;

                $item->update([
                    'quantity_approved' => $itemInput['quantity_approved'],
                    'subtotal' => $newSubtotal
                ]);
            }
        }
        $newTotalAmount = PurchaseOrderItem::where('purchase_id', $purchase->id)->sum('subtotal');
        $purchase->update(['total_amount' => $newTotalAmount]);
    }

    protected function applyExpectedDates(PurchaseOrder $purchase): Carbon
    {
        $maxLeadtime = $purchase->items()
            ->with('product')
            ->get()
            ->map(function ($item) use ($purchase) {
                return ProductSupplierItem::where('product_id', $item->product_id)
                    ->where('supplier_id', $purchase->supplier_id)
                    ->value('lead_time_days') ?? 0;
            })
            ->max() ?? 0;

        return now()->addDays($maxLeadtime);
    }
}
