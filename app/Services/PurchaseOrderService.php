<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Notifications\PurchaseOrderNotification;
use App\Repositories\PurchaseOrderRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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

    public function getSummary()
    {
        return $this->purchaseOrderRepository->getSummary();
    }

    public function getAllPurchaseOrders()
    {
        return $this->purchaseOrderRepository->getAllPaginated();
    }

    public function getAllConfirmations()
    {
        return $this->purchaseOrderRepository->getAllConfirmation();
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

        $purchase = DB::transaction(function () use ($data, $userId, $supplierCatalog) {
            $warehouseId = Auth::user()?->warehouse_id;

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
                        throw new \InvalidArgumentException("Satu atau lebih produk yang dipilih tidak disediakan oleh Supplier tersebut.");
                    }

                    $moq = $supplierCatalog[$productId]->min_order_quantity ?? 1;
                    if ($qtyOrdered < $moq && $isSubmit) {
                        throw new \InvalidArgumentException("Jumlah pesanan untuk suatu produk masih di bawah Minimum Order Quantity (MOQ) yaitu $moq unit.");
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

            $warehouse = DB::table('warehouses')->where('id', $warehouseId)->first();
            $warehouseCode = $warehouse ? strtoupper($warehouse->warehouse_code) : 'WHS';

            $poCode = 'PO-' . $warehouseCode . '-' . strtoupper(Str::random(6));

            $newPurchase = $this->purchaseOrderRepository->createRequest([
                'po_code' => $poCode,
                'created_by' => $userId,
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $warehouseId,
                'total_amount' => $totalAmount,
                'status' => $initialStatus,
            ]);

            $this->purchaseOrderRepository->assignProducts($newPurchase, $processedItems);

            return $newPurchase;
        });

        if ($purchase->status === PurchaseOrderStatus::SUBMITTED) {
            $purchase->load('user');
            $this->sendPurchaseOrderNotification($purchase, PurchaseOrderStatus::SUBMITTED);
        }

        return $purchase->fresh(['warehouse', 'supplier', 'items.product', 'user']);
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

        $updatedPurchase = DB::transaction(function () use ($purchase, $data, $supplierCatalog) {
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
                $this->purchaseOrderRepository->updateStatus($purchase, $newStatus, $data);
            }

            $this->purchaseOrderRepository->updateRequest($purchase, [
                'supplier_id' => $data['supplier_id'],
                'total_amount' => $totalAmount,
            ]);

            $this->purchaseOrderRepository->syncProducts($purchase, $processedItems);

            return $purchase;
        });

        if ($updatedPurchase->status === PurchaseOrderStatus::SUBMITTED) {
            $updatedPurchase->load('user');
            $this->sendPurchaseOrderNotification($updatedPurchase, PurchaseOrderStatus::SUBMITTED);
        }

        return $updatedPurchase->fresh(['warehouse', 'supplier', 'items.product', 'user']);
    }

    public function updateStatusPurchaseOrder(PurchaseOrder $purchase, array $data): PurchaseOrder
    {
        $newStatus = PurchaseOrderStatus::from($data['status']);

        if (!$purchase->status->canTransition($newStatus)) {
            throw new \InvalidArgumentException("Update status failed, Status transition from '{$purchase->status->value}' to '{$newStatus->value}' is not allowed.");
        }

        $updatedPurchase = DB::transaction(function () use ($purchase, $newStatus, $data) {
            $this->purchaseOrderRepository->updateStatus($purchase, $newStatus, $data);

            if (array_key_exists('notes', $data)) {
                $purchase->update(['notes' => $data['notes']]);
            }

            return $purchase->fresh(['warehouse', 'supplier', 'items.product', 'user']);
        });

        $this->sendPurchaseOrderNotification($updatedPurchase, $newStatus);

        return $updatedPurchase;
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

        if (
            $purchase->status !== PurchaseOrderStatus::DRAFT &&
            !$purchase->status->isFinal()
        ) {
            throw new InvalidArgumentException(
                'Purchase Order can only be deleted when its status is Draft or Final.'
            );
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

    public function getTrashedPurchaseOrder()
    {
        return $this->purchaseOrderRepository->getTrashedPaginated();
    }

protected function sendPurchaseOrderNotification(PurchaseOrder $purchase, PurchaseOrderStatus $newStatus): void
{
    $notificationData = match ($newStatus) {
        PurchaseOrderStatus::SUBMITTED => [
            'recipients' => User::role('super-admin')->get(),
            'title' => 'PO Baru Menunggu Approval',
            'message' => "Pengajuan PO {$purchase->po_code} diterbitkan oleh {$purchase->user->name}. Butuh peninjauan segera.",
            'type' => 'warning',
        ],
        PurchaseOrderStatus::APPROVED => [
            'recipients' => collect([$purchase->user]),
            'title' => 'Pengajuan PO Disetujui',
            'message' => "Purchase Order {$purchase->po_code} telah disetujui dan dilanjutkan ke tahap pengadaan.",
            'type' => 'success',
        ],
        PurchaseOrderStatus::ORDERED => [
            'recipients' => $purchase->warehouse->users,
            'title' => 'PO Dalam Pengiriman (Inbound)',
            'message' => "Barang untuk PO {$purchase->po_code} sedang dikirim ke gudang {$purchase->warehouse->name}.",
            'type' => 'info',
        ],
        PurchaseOrderStatus::REJECTED => [
            'recipients' => collect([$purchase->user]),
            'title' => 'Pengajuan PO Ditolak',
            'message' => "PO {$purchase->po_code} ditolak. Alasan: " . ($purchase->notes ?? 'Tidak ada keterangan tambahan.'),
            'type' => 'error',
        ],
        PurchaseOrderStatus::CANCELLED => [
            'recipients' => collect([$purchase->user]),
            'title' => 'Purchase Order Dibatalkan',
            'message' => "Purchase Order {$purchase->po_code} telah dibatalkan di dalam sistem.",
            'type' => 'error',
        ],
        PurchaseOrderStatus::CLOSED => [
            'recipients' => $purchase->warehouse->users,
            'title' => 'Purchase Order Selesai',
            'message' => "Prosedur tuntas. Seluruh barang PO {$purchase->po_code} terkonfirmasi telah masuk ke gudang.",
            'type' => 'success',
        ],
        default => null,
    };

    if ($notificationData && $notificationData['recipients'] && $notificationData['recipients']->isNotEmpty()) {
        Notification::send(
            $notificationData['recipients'],
            new PurchaseOrderNotification(
                $purchase,
                $notificationData['title'],
                $notificationData['message'],
                $notificationData['type'] ?? 'info'
            )
        );
    }
}
}
