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

    protected $purchaseOrderRepository;

    public function __construct(PurchaseOrderRepository $purchaseOrderRepository)
    {
        $this->purchaseOrderRepository = $purchaseOrderRepository;
    }

    public function getAllPurchaseOrders(int $purchasePage = 10, string $userId)
    {
        return $this->purchaseOrderRepository->getAllPaginated($purchasePage, $userId);
    }

    public function createRequestPurchaseOrder(array $data, string $userId): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $userId) {
            $totalAmount = 0;
            $processedItems = [];

            foreach ($data['items'] as $i) {
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

            $poCode = 'PO-' . now()->format('Ymd') . '-' . strtolower(Str::random(8));

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

        return DB::transaction(function () use ($purchase, $data) {
            $this->purchaseOrderRepository->deleteItems($purchase);

            $totalAmount = 0;
            $processedItems = [];

            foreach ($data['items'] as $i) {
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

            $this->purchaseOrderRepository->assignProducts($purchase, $processedItems);

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
