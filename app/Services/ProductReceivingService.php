<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiveStatus;
use App\Models\ProductReceiving;
use App\Models\ProductReceivingItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Repositories\BatchRepository;
use App\Repositories\MutationRepository;
use App\Repositories\ProductReceivingRepository;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Illuminate\Support\Str;

class ProductReceivingService
{
    /**
     * Create a new class instance.
     */

    protected ProductReceivingRepository $productRecivingRepository;
    protected BatchService $batchService;
    protected BatchRepository $batchRepository;
    protected MutationRepository $mutationRepository;

    public function __construct(ProductReceivingRepository $productRecivingRepository, BatchRepository $batchRepository, BatchService $batchService, MutationRepository $mutationRepository)
    {
        $this->productRecivingRepository = $productRecivingRepository;
        $this->batchRepository = $batchRepository;
        $this->mutationRepository = $mutationRepository;
        $this->batchService = $batchService;
    }

    public function getAllReceives(int $receivePage = 10, array $filters)
    {
        return $this->productRecivingRepository->getAllPaginated($receivePage, $filters);
    }

    public function getReceiveDetail(ProductReceiving $receive): ProductReceiving
    {
        $userId = Auth::user()->warehouse_id;

        if ($receive->purchase->warehouse_id !== $userId) {
            throw new AuthorizationException(
                "You are not permitted to view other warehouse."
            );
        }

        return $this->productRecivingRepository->getById($receive);
    }

    public function createReceive(array $data, string $userId): ProductReceiving
    {
        return DB::transaction(function () use ($data, $userId) {
            $purchase = PurchaseOrder::findOrFail($data['purchase_id']);

            if ($purchase->status !== PurchaseOrderStatus::ORDERED) {
                throw new InvalidArgumentException("Product receiving can only be processed for ordered status");
            }

            $warehouseId   = $purchase->warehouse_id;
            $warehouseCode = $purchase->warehouse->warehouse_code;

            $poItems = PurchaseOrderItem::where('purchase_id', $purchase->id)->get()->keyBy('product_id');

            $totalQtyOrdered = 0;
            $totalQtyAccepted = 0;
            $totalQtyRejected = 0;

            $receiveItemsData = [];

            foreach ($data['items'] as $i) {
                $productId = $i['product_id'];
                $accepted = (int) $i['quantity_accepted'];
                $rejected = (int) $i['quantity_rejected'];
                $total = $accepted + $rejected;

                $poItem = $poItems[$productId] ?? null;

                if (!$poItem) {
                    throw new InvalidArgumentException("Product {$poItems->product->name} not found in this purchase order.");
                }

                $qtyOrdered = (int) $poItem->quantity_ordered;

                if ($total !== $qtyOrdered) {
                    throw new InvalidArgumentException("Total Quantity {$total} for {$poItem->product->name} cannot exceed order quantity {$qtyOrdered}.");
                }

                $poItem->update([
                    'quantity_received' => $accepted,
                ]);

                $totalQtyOrdered += $qtyOrdered;
                $totalQtyAccepted += $accepted;
                $totalQtyRejected += $rejected;

                $receiveItemsData[] = [
                    'product_id' => $productId,
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'notes' => $i['notes'],

                    'production_date' => !empty($i['production_date'])
                        ? Carbon::parse($i['production_date'])->format('Y-m-d H:i:s')
                        : null,

                    'expired_date' => !empty($i['expired_date'])
                        ? Carbon::parse($i['expired_date'])->format('Y-m-d H:i:s')
                        : null,
                    'price' => $poItem->unit_price,
                    'condition' => $i['condition'] ?? null,
                ];
            }

            $calculatedStatus = ReceiveStatus::PARTIAL;

            if ($totalQtyAccepted === $totalQtyOrdered) {
                $calculatedStatus = ReceiveStatus::FULL;
            }

            if ($totalQtyRejected === $totalQtyOrdered) {
                $calculatedStatus = ReceiveStatus::REJECT;
            }

            $receiveCode = 'RCV-' . $warehouseCode . '-' . strtoupper(Str::random(6));

            $receive = $this->productRecivingRepository->createReceive([
                'receiving_code' => $receiveCode,
                'purchase_id' => $purchase->id,
                'receiving_date' => now(),
                'receiving_by' => $userId,
                'status' => $calculatedStatus,
            ]);

            $this->productRecivingRepository->assignItems($receive, $receiveItemsData);

            if (in_array($calculatedStatus, [ReceiveStatus::FULL, ReceiveStatus::PARTIAL])) {

                foreach ($receiveItemsData as $item) {
                    if ((int) $item['quantity_accepted'] > 0) {
                        $batch = $this->batchRepository->create([
                            'id' => (string) Str::ulid(),
                            'batch_code' => 'BCH-' . $warehouseCode . '-' . strtoupper(Str::random(6)),
                            'receiving_id' => $receive->id,
                            'product_id' => $item['product_id'],
                            'warehouse_id' => $warehouseId,
                            'initial_quantity' => $item['quantity_accepted'],
                            'current_quantity' => $item['quantity_accepted'],
                            'production_date' => $item['production_date'],
                            'expired_date' => $item['expired_date'],
                            'price' => $item['price'],
                            'condition' => $item['condition'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $this->batchService->generateBarcode($batch);

                        $this->mutationRepository->create([
                            'id' => (string) Str::ulid(),
                            'warehouse_id' => $warehouseId,
                            'batch_id' => $batch->id,
                            'change_quantity' => $item['quantity_accepted'],
                            'before_quantity' => 0,
                            'after_quantity' => $item['quantity_accepted'],
                            'reference_type' => 'RECEIVE',
                            'reference_id' => $receive->id,
                            'notes' => "Received product {$purchase->po_code} to Warehouse {$purchase->warehouse->name}",
                            'status' => 'SUCCESS',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            if ($receive->status === ReceiveStatus::FULL->value) {
                $purchase->update([
                    'status' => PurchaseOrderStatus::CLOSED,
                ]);
            }

            return $receive;
        });
    }

    public function updateItemsAndStatus(ProductReceiving $receive, array $data, string $userId): ProductReceiving
    {
        return DB::transaction(function () use ($receive, $data, $userId) {
            $poItems = PurchaseOrderItem::where('purchase_id', $receive->purchase_id)
                ->get()
                ->keyBy('product_id');

            foreach ($data['items'] as $i) {
                $item = ProductReceivingItem::findOrFail($i['id']);
                $productId = $item->product_id;

                $poItem = $poItems[$productId] ?? null;

                if (!$poItem) {
                    throw new InvalidArgumentException("Product not found in purchase.");
                }

                $accepted = (int) $i['quantity_accepted'];
                $rejected = (int) $i['quantity_rejected'];

                $total = $accepted + $rejected;

                if ($total > $poItem->quantity_received) {
                    throw new InvalidArgumentException("Total quantity for {$item->products->name} cannot exceed {$poItem->quantity_received}.");
                }

                $this->productRecivingRepository->updateItems($item, [
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'notes' => $i['notes'],
                ]);
            }

            if (isset($data['status'])) {

                $newStatus = ReceiveStatus::from($data['status']);

                if (!$receive->status->canTransition($newStatus)) {
                    throw new InvalidArgumentException(
                        "Status transition from '{$receive->status->value}' to '{$newStatus->value}' is not allowed."
                    );
                }

                $this->productRecivingRepository->updateStatus($receive, $newStatus, $userId);
            }

            return $receive->fresh(['items.products', 'user', 'purchase.warehouse']);
        });
    }

    public function softDeleteReceive(ProductReceiving $receive): void
    {
        $isProcess = $receive->status === ReceiveStatus::PROCESS;
        $isFinal = $receive->status->isFinal();

        if (!($isProcess || !$isFinal)) {
            throw new InvalidArgumentException("The deletion rejected. The product receive must have a PENDING status or FINAL Transition.");
        }

        $this->productRecivingRepository->softDelete($receive);
    }

    public function getTrashedReceive(int $perPage)
    {
        return $this->productRecivingRepository->getTrashedPaginated($perPage);
    }

    public function restoreReceive(ProductReceiving $receive)
    {
        $this->productRecivingRepository->restore($receive);

        return $receive->fresh(['items.products', 'user', 'purchase.warehouse']);
    }

    public function forceDeleteReceive(ProductReceiving $receive)
    {
        $this->productRecivingRepository->forceDelete($receive);
    }
}
