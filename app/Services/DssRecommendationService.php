<?php

namespace App\Services;

class DssRecommendationService
{
    protected StockAnalysisService $analysisService;
    protected DssEngine $engine;

    public function __construct(StockAnalysisService $analysisService, DssEngine $engine)
    {
        $this->analysisService = $analysisService;
        $this->engine = $engine;
    }

    public function recommend(?int $historyDays = null): array
    {
        $historyDays = $historyDays ?: config('dss.default_history_days', 30);
        $warehouseActivities = $this->engine->getWarehouseActivities($historyDays);

        $productIds = collect($this->analysisService->analyze($historyDays))->pluck('product_id')->unique()->toArray();
        $supplierItems = \Illuminate\Support\Facades\DB::table('product_supplier_items')
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');

        return array_map(function (array $row) use ($historyDays, $warehouseActivities, $supplierItems) {
            $warehouseActivity = $warehouseActivities[$row['warehouse_id']] ?? [
                'activity_score' => 0,
                'warehouse_activity' => 'INACTIVE',
            ];
            
            $productSupplierIds = isset($supplierItems[$row['product_id']]) 
                ? $supplierItems[$row['product_id']]->pluck('supplier_id')->toArray() 
                : [];

            $supplierPrice = $row['price'] ?? 0;
            if (isset($supplierItems[$row['product_id']]) && $supplierItems[$row['product_id']]->isNotEmpty()) {
                $preferred = $supplierItems[$row['product_id']]->firstWhere('is_preferred', 1) 
                          ?? $supplierItems[$row['product_id']]->first();
                if ($preferred && isset($preferred->unit_price)) {
                    $supplierPrice = $preferred->unit_price;
                }
            }

            if ($row['category'] === StockAnalysisService::CATEGORY_FAST_MOVING) {
                $source = $this->engine->findSlowMovingSource(
                    $row['product_id'],
                    $row['warehouse_id'],
                    $historyDays,
                    $warehouseActivities
                );

                $suggestedQty = 0;
                if ($source) {
                    $targetQty = max(($row['avg_daily_out'] * 14) - $row['current_quantity'], 0);
                    $donorLimit = floor($source['current_quantity'] * 0.5);
                    $suggestedQty = (int) min($targetQty, $donorLimit);
                }

                $isTransfer = $source && $suggestedQty > 0;
                $restockQty = (int) max(($row['avg_daily_out'] * 30) - $row['current_quantity'], 0);

                return [
                    'batch_id'           => $row['batch_id'],
                    'product_id'         => $row['product_id'],
                    'product_name'       => $row['product_name'],
                    'warehouse_id'       => $row['warehouse_id'],
                    'warehouse_name'     => $row['warehouse_name'],
                    'category'           => $row['category'],
                    'warehouse_activity' => $warehouseActivity['warehouse_activity'],
                    'activity_score'     => $warehouseActivity['activity_score'],
                    'price'              => $supplierPrice,
                    'recommendation'     => $isTransfer ? 'TRANSFER_IN' : ($restockQty > 0 ? 'RESTOCK' : 'HOLD'),
                    'suggested_qty'      => $isTransfer ? $suggestedQty : $restockQty,
                    'from_warehouse'     => $isTransfer ? $source['warehouse_name'] : null,
                    'from_warehouse_id'  => $isTransfer ? $source['warehouse_id'] : null,
                    'supplier_ids'       => $productSupplierIds,
                    'expired_date'       => $row['expired_date'] ?? null,
                ];
            }

            if ($row['category'] === StockAnalysisService::CATEGORY_SLOW_MOVING) {
                $destination = $this->engine->findFastMovingDestination(
                    $row['product_id'],
                    $row['warehouse_id'],
                    $historyDays,
                    $warehouseActivities
                );

                $suggestedQty = 0;
                if ($destination) {
                    $sourceQty = max($row['current_quantity'] - ($row['avg_daily_out'] * 60), 0);
                    $receiverNeed = max($destination['avg_daily_out'] * 14, 0);
                    $suggestedQty = (int) min($sourceQty, $receiverNeed);
                }

                $isTransfer = $destination && $suggestedQty > 0;

                if ($isTransfer) {
                    return [
                        'batch_id'           => $row['batch_id'],
                        'product_id'         => $row['product_id'],
                        'product_name'       => $row['product_name'],
                        'warehouse_id'       => $row['warehouse_id'],
                        'warehouse_name'     => $row['warehouse_name'],
                        'category'           => $row['category'],
                        'warehouse_activity' => $warehouseActivity['warehouse_activity'],
                        'activity_score'     => $warehouseActivity['activity_score'],
                        'price'              => $supplierPrice,
                        'recommendation'     => 'TRANSFER_OUT',
                        'suggested_qty'      => $suggestedQty,
                        'to_warehouse'       => $destination['warehouse_name'] ?? null,
                        'to_warehouse_id'    => $destination['warehouse_id'] ?? null,
                        'supplier_ids'       => $productSupplierIds,
                        'expired_date'       => $row['expired_date'] ?? null,
                    ];
                }

                return [
                    'batch_id'           => $row['batch_id'],
                    'product_id'         => $row['product_id'],
                    'product_name'       => $row['product_name'],
                    'warehouse_id'       => $row['warehouse_id'],
                    'warehouse_name'     => $row['warehouse_name'],
                    'category'           => $row['category'],
                    'warehouse_activity' => $warehouseActivity['warehouse_activity'],
                    'activity_score'     => $warehouseActivity['activity_score'],
                    'price'              => $supplierPrice,
                    'recommendation'     => 'SLOW_MOVING_ALERT',
                    'suggested_qty'      => 0,
                    'to_warehouse'       => null,
                    'to_warehouse_id'    => null,
                    'supplier_ids'       => $productSupplierIds,
                    'expired_date'       => $row['expired_date'] ?? null,
                ];
            }

            return [
                'batch_id'           => $row['batch_id'],
                'product_id'         => $row['product_id'],
                'product_name'       => $row['product_name'],
                'warehouse_id'       => $row['warehouse_id'],
                'warehouse_name'     => $row['warehouse_name'],
                'category'           => $row['category'],
                'warehouse_activity' => $warehouseActivity['warehouse_activity'],
                'activity_score'     => $warehouseActivity['activity_score'],
                'price'              => $supplierPrice,
                'recommendation'     => 'HOLD',
                'suggested_qty'      => 0,
                'from_warehouse'     => null,
                'to_warehouse'       => null,
                'supplier_ids'       => $productSupplierIds,
                'expired_date'       => $row['expired_date'] ?? null,
            ];
        }, $this->analysisService->analyze($historyDays));
    }

    /**
     * Calculate equal allocation for push distribution.
     * @param string $productId
     * @param string $warehouseId
     * @param array $storeIds
     * @param int $totalAvailableStock
     * @param bool $isUrgent
     * @param \App\Repositories\StockMutationRepository $mutationRepo
     * @return array
     */
    public function calculatePushDistributionAllocation(
        string $productId,
        string $warehouseId,
        array $storeIds,
        int $totalAvailableStock,
        bool $isUrgent,
        \App\Repositories\StockMutationRepository $mutationRepo
    ): array {
        $pushStock = $totalAvailableStock;
        $safetyStock = 0;

        if (!$isUrgent) {
            // Get last 30 days outward distribution for this product from this warehouse
            $safetyStock = $mutationRepo->getProductWarehouseOutboundMovement($productId, $warehouseId, 30);
            $pushStock = max(0, $totalAvailableStock - $safetyStock);
        }

        $allocation = [];
        $storeCount = count($storeIds);

        if ($storeCount > 0 && $pushStock > 0) {
            $baseQty = (int) floor($pushStock / $storeCount);
            $remainder = $pushStock % $storeCount;

            foreach ($storeIds as $storeId) {
                $qty = $baseQty;
                if ($remainder > 0) {
                    $qty++;
                    $remainder--;
                }
                $allocation[$storeId] = $qty;
            }
        } else {
            foreach ($storeIds as $storeId) {
                $allocation[$storeId] = 0;
            }
        }

        return [
            'total_available_stock' => $totalAvailableStock,
            'is_urgent'             => $isUrgent,
            'safety_stock'          => $safetyStock,
            'push_stock'            => $pushStock,
            'allocation'            => $allocation,
        ];
    }

    /**
     * Calculate equal allocation for multi-product push distribution.
     * Groups allocations by store_id.
     */
    public function calculateMultiPushDistributionAllocation(
        array $products,
        string $warehouseId,
        array $storeIds,
        \App\Repositories\StockMutationRepository $mutationRepo
    ): array {
        $storeAllocations = [];
        foreach ($storeIds as $storeId) {
            $storeAllocations[$storeId] = [];
        }

        foreach ($products as $prod) {
            $productId = $prod['product_id'];
            $isUrgent = $prod['is_urgent'] ?? false;
            $totalStock = $prod['total_available_stock'] ?? 0;
            
            $pushStock = $totalStock;
            $safetyStock = 0;

            if (!$isUrgent) {
                $safetyStock = $mutationRepo->getProductWarehouseOutboundMovement($productId, $warehouseId, 30);
                $pushStock = max(0, $totalStock - $safetyStock);
            }

            $storeCount = count($storeIds);
            
            if ($storeCount > 0 && $pushStock > 0) {
                $baseQty = (int) floor($pushStock / $storeCount);
                $remainder = $pushStock % $storeCount;

                foreach ($storeIds as $storeId) {
                    $qty = $baseQty;
                    if ($remainder > 0) {
                        $qty++;
                        $remainder--;
                    }
                    $storeAllocations[$storeId][] = [
                        'product_id' => $productId,
                        'product_name' => $prod['product_name'] ?? '',
                        'suggested_qty' => $qty,
                        'total_available_stock' => $totalStock,
                        'safety_stock' => $safetyStock,
                        'is_urgent' => $isUrgent
                    ];
                }
            } else {
                foreach ($storeIds as $storeId) {
                    $storeAllocations[$storeId][] = [
                        'product_id' => $productId,
                        'product_name' => $prod['product_name'] ?? '',
                        'suggested_qty' => 0,
                        'total_available_stock' => $totalStock,
                        'safety_stock' => $safetyStock,
                        'is_urgent' => $isUrgent
                    ];
                }
            }
        }

        return $storeAllocations;
    }
}
