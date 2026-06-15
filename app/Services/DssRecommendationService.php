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
                    'price'              => $row['price'] ?? 0,
                    'recommendation'     => $isTransfer ? 'TRANSFER_IN' : ($restockQty > 0 ? 'RESTOCK' : 'HOLD'),
                    'suggested_qty'      => $isTransfer ? $suggestedQty : $restockQty,
                    'from_warehouse'     => $isTransfer ? $source['warehouse_name'] : null,
                    'from_warehouse_id'  => $isTransfer ? $source['warehouse_id'] : null,
                    'supplier_ids'       => $productSupplierIds,
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
                        'price'              => $row['price'] ?? 0,
                        'recommendation'     => 'TRANSFER_OUT',
                        'suggested_qty'      => $suggestedQty,
                        'to_warehouse'       => $destination['warehouse_name'] ?? null,
                        'to_warehouse_id'    => $destination['warehouse_id'] ?? null,
                        'supplier_ids'       => $productSupplierIds,
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
                    'price'              => $row['price'] ?? 0,
                    'recommendation'     => 'SLOW_MOVING_ALERT',
                    'suggested_qty'      => 0,
                    'to_warehouse'       => null,
                    'to_warehouse_id'    => null,
                    'supplier_ids'       => $productSupplierIds,
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
                'price'              => $row['price'] ?? 0,
                'recommendation'     => 'HOLD',
                'suggested_qty'      => 0,
                'from_warehouse'     => null,
                'to_warehouse'       => null,
                'supplier_ids'       => $productSupplierIds,
            ];
        }, $this->analysisService->analyze($historyDays));
    }
}
