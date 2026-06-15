<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiveStatus;
use App\Enums\RestockStatus;
use App\Enums\TransferStatus;
use App\Models\ProductReceiving;
use App\Models\PurchaseOrder;
use App\Models\Restock;
use App\Models\StockTransfers;
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

    public function getAllReceives()
    {
        return $this->productRecivingRepository->getAll();
    }

    public function getReceiveDetail(ProductReceiving $receive): ProductReceiving
    {
        $userId = Auth::user()->warehouse_id;

        $sourceWarehouseId = $receive->receivable_type === 'transfer'
            ? $receive->receivable->to_warehouse_id
            : $receive->receivable->warehouse_id;

        if ($sourceWarehouseId !== $userId) {
            throw new AuthorizationException(
                "You are not permitted to view other warehouse."
            );
        }

        return $this->productRecivingRepository->getById($receive);
    }

    public function createReceive(array $data, string $userId): ProductReceiving
    {
        return DB::transaction(function () use ($data, $userId) {
            $context = $this->resolveReceivableContext($data['receivable_type'], $data['receivable_id']);

            $sourceModel   = $context['model'];
            $warehouseId   = $context['warehouse_id'];
            $warehouseCode = $context['warehouse_code'];
            $sourceItems   = $context['items'];
            $documentCode  = $context['document_code'];

            $totalQtyOrdered = 0;
            $totalQtyAccepted = 0;
            $totalQtyRejected = 0;

            $receiveItemsData = [];

            foreach ($data['items'] as $i) {
                $productId = $i['product_id'];
                $accepted = (int) $i['quantity_accepted'];
                $rejected = (int) $i['quantity_rejected'];
                $total = $accepted + $rejected;

                $sourceItem = $sourceItems[$productId] ?? null;

                if (!$sourceItem) {
                    throw new InvalidArgumentException("Product {$sourceItem->product->name} not found in this purchase order.");
                }

                $qtyOrdered = (int) $sourceItem->expected_quantity;

                if ($total !== $qtyOrdered) {
                    throw new InvalidArgumentException("Total Quantity {$total} for {$sourceItem->product->name} cannot exceed order quantity {$qtyOrdered}.");
                }

                if ($data['receivable_type'] === 'purchase_order') {
                    $sourceItem->original_model->update([
                        'quantity_received' => $accepted,
                    ]);
                }

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
                    'price' => $sourceItem->price,
                    'condition' => $i['condition'] ?? null,
                    'racks' => $i['racks'],
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
                'receiving_code'  => $receiveCode,
                'receivable_type' => $data['receivable_type'],
                'receivable_id'   => $data['receivable_id'],
                'receiving_date'  => now(),
                'receiving_by'    => $userId,
                'status'          => $calculatedStatus,
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

                        $this->batchService->assignLocation($batch, $item['racks']);

                        $this->mutationRepository->create([
                            'id' => (string) Str::ulid(),
                            'warehouse_id' => $warehouseId,
                            'batch_id' => $batch->id,
                            'change_quantity' => $item['quantity_accepted'],
                            'before_quantity' => 0,
                            'after_quantity' => $item['quantity_accepted'],
                            'reference_type' => 'RECEIVE',
                            'reference_id' => $receive->id,
                            'notes' => "Received product {$documentCode} to Warehouse {$warehouseCode}",
                            'status' => 'SUCCESS',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            return $receive;
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

    public function getTrashedReceive()
    {
        return $this->productRecivingRepository->getTrashedPaginated();
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

    public function getAvailableDocuments(string $type)
    {
        $warehouseId = Auth::user()->warehouse_id;

        return match ($type) {
            'purchase_order' => PurchaseOrder::where('warehouse_id', $warehouseId)
                ->where('status', 'ordered')
                ->get(['id', 'po_code as label']),

            'transfer' => StockTransfers::where('to_warehouse_id', $warehouseId)
                ->where('status', 'completed')
                ->get(['id', 'transfer_code as label']),

            'restock' => Restock::where('warehouse_id', $warehouseId)
                ->where('status', 'restocked')
                ->get(['id', 'restock_code as label']),

            default => collect([]),
        };
    }

    private function resolveReceivableContext(string $type, string $id): array
    {
        switch ($type) {
            case 'purchase_order':
                $model = PurchaseOrder::with('warehouse', 'items.product')->findOrFail($id);

                if ($model->status !== PurchaseOrderStatus::ORDERED) {
                    throw new InvalidArgumentException("Product receiving can only be processed for ordered status");
                }

                $items = $model->items->mapWithKeys(fn($item) => [$item->product_id => (object)[
                    'expected_quantity' => $item->quantity_ordered,
                    'received_quantity' => $item->quantity_received ?? 0,
                    'price'             => $item->unit_price,
                    'original_model'    => $item
                ]]);

                return [
                    'model'          => $model,
                    'warehouse_id'   => $model->warehouse_id,
                    'warehouse_code' => $model->warehouse->warehouse_code,
                    'document_code'  => $model->po_code,
                    'items'          => $items,
                ];

            case 'transfer':
                $model = StockTransfers::with('toWarehouse', 'products')->findOrFail($id);

                if ($model->status !== TransferStatus::COMPLETED) {
                    throw new InvalidArgumentException("Transfers must be completed to be received.");
                }

                $items = collect([$model])->mapWithKeys(fn($item) => [$item->product_id => (object)[
                    'expected_quantity' => $item->approved_quantity,
                    'received_quantity' => 0,
                    'price'             => 0,
                    'original_model'    => $item
                ]]);

                return [
                    'model'          => $model,
                    'warehouse_id'   => $model->to_warehouse_id,
                    'warehouse_code' => $model->toWarehouse->warehouse_code,
                    'document_code'  => $model->transfer_code,
                    'items'          => $items,
                ];

            case 'restock':
                $model = Restock::with('warehouse', 'item.product')->findOrFail($id);

                if ($model->status !== RestockStatus::RESTOCKED) {
                    throw new InvalidArgumentException("Restocks must be completed to be received.");
                }

                $items = $model->item->mapWithKeys(fn($item) => [$item->product_id => (object)[
                    'expected_quantity' => $item->requested_quantity,
                    'received_quantity' => 0,
                    'price'             => 0,
                    'original_model'    => $item
                ]]);

                return [
                    'model'          => $model,
                    'warehouse_id'   => $model->warehouse_id,
                    'warehouse_code' => $model->warehouse->warehouse_code,
                    'document_code'  => $model->restock_code,
                    'items'          => $items,
                ];

            default:
                throw new InvalidArgumentException("Unsupported receivable type: {$type}");
        }
    }
}
