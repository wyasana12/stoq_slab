<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PurchaseOrderService
{
    /**
     * Create a new class instance.
     */

    protected PurchaseOrderRepository $purchaseOrderRepository;

    public function __construct(PurchaseOrderRepository $purchaseOrderRepository)
    {
        $this->purchaseOrderRepository = $purchaseOrderRepository;
    }

    public function getAllPurchaseOrders(int $purchasePage = 10, string $userId)
    {
        return $this->purchaseOrderRepository->getAllPaginated($purchasePage, $userId);
    }

    public function getAllConfirmations(int $purchasePage = 10)
    {
        return $this->purchaseOrderRepository->getAllConfirmation($purchasePage);    
    }

    public function createRequestPurchaseOrder(array $data, string $userId): PurchaseOrder
    {
        $items = $data['items'] ?? [];

        $productIds = collect($items)->pluck('product_id')->filter();

        if ($productIds->duplicates()->isNotEmpty()) {
            throw new \InvalidArgumentException(
                'Duplicate products are not allowed in a single purchase order.'
            );
        }

        $supplierCatalog = DB::table('product_supplier_items')
            ->where('supplier_id', $data['supplier_id'])
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        return DB::transaction(function () use ($data, $userId, $supplierCatalog) {
            $totalAmount = 0;
            $processedItems = [];
            $isSubmit = isset($data['status']) && $data['status'] === PurchaseOrderStatus::SUBMITTED->value;

            foreach ($data['items'] as $i) {
                $productId = $i['product_id'] ?? null;
                if (!$productId) continue;

                $qtyOrdered = $i['quantity_ordered'] ?? 1;
                $unitPrice = $i['unit_price'] ?? 0;

                if (!empty($data['supplier_id'])) {
                    if (!$supplierCatalog->has($productId) && $isSubmit) {
                        throw new \InvalidArgumentException("One or more selected products are not supplied by the chosen supplier.");
                    }

                    $moq = $supplierCatalog[$productId]->min_order_quantity ?? 1;
                    if ($qtyOrdered < $moq && $isSubmit) {
                        throw new \InvalidArgumentException("The order quantity for a product is below the required Minimum order quantity $moq");
                    }
                }

                $subTotal = $i['quantity_ordered'] * $i['unit_price'];
                $totalAmount += $subTotal;

                $processedItems[] = [
                    'product_id' => $i['product_id'],
                    'quantity_ordered' => $i['quantity_ordered'],
                    'unit_price' => $i['unit_price'],
                    'subtotal' => $subTotal,
                ];
            }

            $initialStatus = isset($data['status'])
                ? PurchaseOrderStatus::from($data['status'])
                : PurchaseOrderStatus::DRAFT;

            $warehouse = DB::table('warehouses')->where('id', $data['warehouse_id'])->first();
            $warehouseCode = $warehouse ? strtoupper($warehouse->warehouse_code) : 'WHS';

            $poCode = 'PO-' . $warehouseCode . '-' . strtoupper(Str::random(6));

            $purchase = $this->purchaseOrderRepository->createRequest([
                'po_code' => $poCode,
                'created_by' => $userId,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'total_amount' => $totalAmount,
                'status' => $initialStatus,
            ]);

            $this->purchaseOrderRepository->assignProducts($purchase, $processedItems);

            return $purchase;
        });
    }

    public function updateRequestPurchaseOrder(PurchaseOrder $purchase, array $data, string $userId): PurchaseOrder
    {
        if ($purchase->created_by !== $userId) {
            throw new \InvalidArgumentException("Update request failed, only same user can only updated.");
        }
        if ($purchase->status !== PurchaseOrderStatus::DRAFT) {
            throw new \InvalidArgumentException("Update request failed, only status draft can only updated.");
        }

        $productIds = collect($data['items'])->pluck('product_id');

        if ($productIds->duplicates()->isNotEmpty()) {
            throw new \InvalidArgumentException(
                'Duplicate products are not allowed in a single purchase order.'
            );
        }

        $supplierCatalog = DB::table('product_supplier_items')
            ->where('supplier_id', $data['supplier_id'])
            ->whereIn('product_id', $productIds)
            ->get()
            ->keyBy('product_id');

        return DB::transaction(function () use ($purchase, $data, $supplierCatalog) {
            $totalAmount = 0;
            $processedItems = [];

            foreach ($data['items'] as $i) {
                $productId = $i['product_id'];
                $qtyOrdered = $i['quantity_ordered'];

                if (!$supplierCatalog->has($productId)) {
                    throw new InvalidArgumentException("One or more selected products are not supplied by the chosen supplier.");
                }

                $moq = $supplierCatalog[$productId]->min_order_quantity;
                if ($qtyOrdered < $moq) {
                    throw new InvalidArgumentException("The order quantity for a product is below the required Minimum order quantity $moq");
                }

                $subTotal = $i['quantity_ordered'] * $i['unit_price'];

                $totalAmount += $subTotal;

                $processedItems[] = [
                    'product_id' => $i['product_id'],
                    'quantity_ordered' => $i['quantity_ordered'],
                    'unit_price' => $i['unit_price'],
                    'subtotal' => $subTotal
                ];
            }

            $newStatus = isset($data['status'])
                ?   PurchaseOrderStatus::from($data['status'])
                : $purchase->status;

            if ($newStatus !== $purchase->status) {
                $this->purchaseOrderRepository->updateStatus($purchase, $newStatus);
            }

            $this->purchaseOrderRepository->updateRequest($purchase, [
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'total_amount' => $totalAmount,
            ]);

            $this->purchaseOrderRepository->syncProducts($purchase, $processedItems);

            return $purchase->fresh(['warehouse', 'supplier', 'items.product', 'user']);
        });
    }

    public function updateStatusPurchaseOrder(PurchaseOrder $purchase, array $data): PurchaseOrder
    {
        $newStatus = PurchaseOrderStatus::from($data['status']);

        if (!$purchase->status->canTransition($newStatus)) {
            throw new \InvalidArgumentException("Update status failed, Status transition from '{$purchase->status->value}' to '{$newStatus->value}' is not allowed.");
        }

        return DB::transaction(function () use ($purchase, $newStatus, $data) {
            $this->purchaseOrderRepository->updateStatus($purchase, $newStatus);

            if (array_key_exists('notes', $data)) {
                $purchase->update([
                    'notes' => $data['notes']
                ]);
            }

            return $purchase->fresh(['warehouse', 'supplier', 'items.product', 'user']);
        });
    }

    public function getPurchaseOrderDetail(PurchaseOrder $purchase, string $userId): PurchaseOrder
    {
        if ($purchase->status === PurchaseOrderStatus::DRAFT && $purchase->created_by !== $userId) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                "You are not permitted to view other people's purchase order drafts."
            );
        }
        return $this->purchaseOrderRepository->getById($purchase);
    }

    public function softDeletePurchaseOrder(PurchaseOrder $purchase, string $userId): void
    {
        if ($purchase->created_by !== $userId) {
            throw new AuthorizationException("You are not permitted to delete this Purchase Order.");
        }

        $isDraft = $purchase->status === PurchaseOrderStatus::DRAFT;
        $isFinal = $purchase->status->isFinal();

        if (!($isDraft || !$isFinal)) {
            throw new InvalidArgumentException("The deletion rejected. The purchase order must have a DRAFT status or FINAL Transition.");
        }

        $this->purchaseOrderRepository->softDelete($purchase);
    }

    public function restorePurchaseOrder(PurchaseOrder $purchase, string $userId): PurchaseOrder
    {
        if ($purchase->created_by !== $userId) {
            throw new AuthorizationException("You are not permitted to restore this purchase order.");
        }

        $this->purchaseOrderRepository->restore($purchase);

        return $purchase->fresh(['warehouse', 'supplier', 'items.product', 'user']);
    }

    public function forceDeletePurchaseOrder(PurchaseOrder $purchase, string $userId)
    {
        if ($purchase->created_by !== $userId) {
            throw new AuthorizationException("You are not permitted to force delete this purchase order.");
        }

        $this->purchaseOrderRepository->forceDelete($purchase);
    }

    public function getTrashedPurchaseOrder(int $perPage, string $userId)
    {
        return $this->purchaseOrderRepository->getTrashedPaginated($perPage, $userId);
    }
}
