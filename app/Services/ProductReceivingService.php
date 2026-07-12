<?php

namespace App\Services;

use App\Enums\PurchaseOrderStatus;
use App\Enums\ReceiveStatus;
use App\Enums\RestockStatus;
use App\Enums\TransferStatus;
use App\Models\Batch;
use App\Models\ProductReceiving;
use App\Models\PurchaseOrder;
use App\Models\Restock;
use App\Models\StockTransfers;
use App\Models\User;
use App\Notifications\ReceivingNotification;
use App\Repositories\BatchRepository;
use App\Repositories\MutationRepository;
use App\Repositories\ProductReceivingRepository;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Illuminate\Support\Str;

class ProductReceivingService
{
    /**
     * Create a new class instance.
     */

    protected ProductReceivingRepository $productReceivingRepository;
    protected BatchService $batchService;
    protected BatchRepository $batchRepository;
    protected MutationRepository $mutationRepository;

    public function __construct(ProductReceivingRepository $productReceivingRepository, BatchRepository $batchRepository, BatchService $batchService, MutationRepository $mutationRepository)
    {
        $this->productReceivingRepository = $productReceivingRepository;
        $this->batchRepository = $batchRepository;
        $this->mutationRepository = $mutationRepository;
        $this->batchService = $batchService;
    }

    public function getStats()
    {
        return $this->productReceivingRepository->getStats();
    }

    public function getAllReceives()
    {
        return $this->productReceivingRepository->getAll();
    }

    public function getReceiveDetail(ProductReceiving $receive): ProductReceiving
    {
        /** @var User $user */
        $user = Auth::user();
        $userId = $user->warehouse_id;

        $sourceWarehouseId = $receive->receivable_type === 'transfer'
            ? $receive->receivable->to_warehouse_id
            : $receive->receivable->warehouse_id;

        if (!$user->hasRole('super-admin') && $sourceWarehouseId !== $userId) {
            throw new AuthorizationException(
                "You are not permitted to view other warehouse."
            );
        }

        return $this->productReceivingRepository->getById($receive);
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
            $batchesData = [];
            $notificationBatchDetails = [];

            $groupedItems = collect($data['items'])->groupBy('product_id');
            foreach ($groupedItems as $productId => $batches) {
                $sourceItem = $sourceItems[$productId] ?? null;

                if (!$sourceItem) {
                    throw new InvalidArgumentException("Product {$sourceItem->product->name} not found in this purchase order.");
                }

                $qtyOrdered = (int) $sourceItem->expected_quantity;

                $totalAcceptedForProduct = $batches->sum('quantity_accepted');

                if ($totalAcceptedForProduct > $qtyOrdered) {
                    throw new InvalidArgumentException("Total Quantity {$totalAcceptedForProduct} for product cannot exceed order quantity {$qtyOrdered}.");
                }

                $qtyRejectedForProduct = $qtyOrdered - $totalAcceptedForProduct;

                if ($data['receivable_type'] === 'purchase_order') {
                    $sourceItem->original_model->update([
                        'quantity_received' => $totalAcceptedForProduct,
                    ]);
                }

                $totalQtyOrdered += $qtyOrdered;
                $totalQtyAccepted += $totalAcceptedForProduct;
                $totalQtyRejected += $qtyRejectedForProduct;

                $receiveItemsData[] = [
                    'id' => (string) Str::ulid(),
                    'product_id'        => $productId,
                    'quantity_accepted' => $totalAcceptedForProduct,
                    'quantity_rejected' => $qtyRejectedForProduct,
                ];

                foreach ($batches as $batchData) {
                    $accepted = (int) $batchData['quantity_accepted'];

                    if ($accepted <= 0) {
                        continue;
                    }

                    $batchesData[] = [
                        'product_id'        => $productId,
                        'quantity_accepted' => $accepted,
                        'production_date'   => !empty($batchData['production_date'])
                            ? Carbon::parse($batchData['production_date'])->format('Y-m-d')
                            : null,
                        'expired_date'      => !empty($batchData['expired_date'])
                            ? Carbon::parse($batchData['expired_date'])->format('Y-m-d')
                            : null,
                        'price'             => $sourceItem->price,
                        'location_id' => $batchData['location_id']
                    ];
                }
            }

            $calculatedStatus = ReceiveStatus::PARTIAL;

            if ($totalQtyAccepted === $totalQtyOrdered) {
                $calculatedStatus = ReceiveStatus::FULL;
            }

            if ($totalQtyRejected === $totalQtyOrdered) {
                $calculatedStatus = ReceiveStatus::REJECT;
            }

            $receiveCode = 'RCV-' . $warehouseCode . '-' . strtoupper(Str::random(6));

            $receive = $this->productReceivingRepository->createReceive([
                'receiving_code'  => $receiveCode,
                'receivable_type' => $data['receivable_type'],
                'receivable_id'   => $data['receivable_id'],
                'receiving_date'  => now(),
                'receiving_by'    => $userId,
                'notes' => $data['notes'],
                'status'          => $calculatedStatus,
            ]);

            $this->productReceivingRepository->assignItems($receive, $receiveItemsData);

            if (in_array($calculatedStatus, [ReceiveStatus::FULL, ReceiveStatus::PARTIAL])) {

                foreach ($batchesData as $item) {
                    if ((int) $item['quantity_accepted'] > 0) {
                        $batchCode = 'BCH-' . $warehouseCode . '-' . strtoupper(Str::random(6));

                        $batch = $this->batchRepository->create([
                            'id' => (string) Str::ulid(),
                            'batch_code' => $batchCode,
                            'receiving_id' => $receive->id,
                            'product_id' => $item['product_id'],
                            'warehouse_id' => $warehouseId,
                            'initial_quantity' => $item['quantity_accepted'],
                            'current_quantity' => $item['quantity_accepted'],
                            'production_date' => $item['production_date'],
                            'expired_date' => $item['expired_date'],
                            'price' => $item['price'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        $this->batchService->generateBarcode($batch);

                        $manualLocation = $item['location_id'] ?? [];

                        $this->batchService->assignLocation($batch, $manualLocation);

                        $notificationBatchDetails[] = [
                            'code' => $batchCode,
                        ];

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

            if ($data['receivable_type'] === 'purchase_order') {
                $sourceModel->update(['status' => PurchaseOrderStatus::CLOSED]);
            } elseif ($data['receivable_type'] === 'transfer') {
                $sourceModel->update(['status' => TransferStatus::COMPLETED]);
            } elseif ($data['receivable_type'] === 'restock') {
                $sourceModel->update(['status' => RestockStatus::COMPLETED]);
            }

            $this->sendReceivingNotification($receive, $notificationBatchDetails, $warehouseId, $calculatedStatus);

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

        $this->productReceivingRepository->softDelete($receive);
    }

    public function getTrashedReceive()
    {
        return $this->productReceivingRepository->getTrashedPaginated();
    }

    public function restoreReceive(ProductReceiving $receive)
    {
        $this->productReceivingRepository->restore($receive);

        return $receive->fresh(['items.products', 'user', 'purchase.warehouse']);
    }

    public function forceDeleteReceive(ProductReceiving $receive)
    {
        $this->productReceivingRepository->forceDelete($receive);
    }

    public function getAvailableDocuments(string $type)
    {
        /** @var User $user */
        $user = Auth::user();
        $warehouseId = $user->warehouse_id;
        $isSuperAdmin = $user->hasRole('super-admin');

        return match ($type) {
            'purchase_order' => PurchaseOrder::when(!$isSuperAdmin, function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
                ->where('status', 'ordered')
                ->get(['id', 'po_code as label']),

            'transfer' => StockTransfers::when(!$isSuperAdmin, function ($query) use ($warehouseId) {
                $query->where('to_warehouse_id', $warehouseId);
            })
                ->where('status', 'on_delivery')
                ->get(['id', 'transfer_code as label']),

            'restock' => Restock::when(!$isSuperAdmin, function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
                ->where('status', 'on_delivery')
                ->get(['id', 'restock_code as label']),

            default => collect([]),
        };
    }

    protected function sendReceivingNotification(ProductReceiving $receive, array $batchDetails, string $warehouseId, ReceiveStatus $status): void
    {
        $staff = User::role('staff')->where('warehouse_id', $warehouseId)->get();

        if ($staff->isNotEmpty() && count($batchDetails) > 0) {
            $batchItemsString = collect($batchDetails)
                ->map(fn($b) => "{$b['code']}")
                ->implode(', ');

            Notification::send(
                $staff,
                new ReceivingNotification(
                    $receive,
                    'Barang Masuk & Alokasi Rak',
                    "Dokumen {$receive->receivable_type} selesai diproses. Segera letakkan item berikut ke rak: {$batchItemsString}.",
                    'info'
                )
            );
        }

        $admin = User::role('admin')->where('warehouse_id', $warehouseId)->get();

        if ($admin->isNotEmpty()) {
            $statusLabel = strtoupper($status->value);
            $docType = strtoupper(str_replace('_', ' ', $receive->receivable_type));

            $typeColor = match($status) {
                ReceiveStatus::FULL => 'success',
                ReceiveStatus::PARTIAL => 'warning',
                default => 'error'
            };

            Notification::send(
                $admin,
                new ReceivingNotification(
                    $receive,
                    "Penerimaan {$docType} Selesai",
                    "Proses penerimaan untuk dokumen kode {$receive->receiving_code} telah diselesaikan dengan status akhir {$statusLabel}.",
                    $typeColor
                )
            );
        }
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
                    'expected_quantity' => $item->quantity_approved,
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

                if ($model->status !== TransferStatus::ON_DELIVERY) {
                    throw new InvalidArgumentException("Transfers must be on delivery to be received.");
                }

                $items = collect([$model])->mapWithKeys(function ($item) use ($model) {
                    $previousBatch = Batch::where('product_id', $item->product_id)
                        ->where('warehouse_id', $model->from_warehouse_id)
                        ->latest('created_at')
                        ->first();

                    $transferPrice = $previousBatch ? $previousBatch->price : 0;

                    return [$item->product_id => (object)[
                        'expected_quantity' => $item->approved_quantity,
                        'received_quantity' => 0,
                        'price'             => $transferPrice,
                        'original_model'    => $item
                    ]];
                });

                return [
                    'model'          => $model,
                    'warehouse_id'   => $model->to_warehouse_id,
                    'warehouse_code' => $model->toWarehouse->warehouse_code,
                    'document_code'  => $model->transfer_code,
                    'items'          => $items,
                ];

            case 'restock':
                $model = Restock::with('warehouse', 'item.product')->findOrFail($id);

                if ($model->status !== RestockStatus::ON_DELIVERY) {
                    throw new InvalidArgumentException("Restocks must be completed to be received.");
                }

                $items = $model->item->mapWithKeys(fn($item) => [$item->product_id => (object)[
                    'expected_quantity' => $item->requested_quantity,
                    'received_quantity' => 0,
                    'price'             => $item->unit_price,
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
