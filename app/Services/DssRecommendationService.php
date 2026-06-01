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

        return array_map(function (array $row) use ($historyDays, $warehouseActivities) {
            $warehouseActivity = $warehouseActivities[$row['warehouse_id']] ?? [
                'activity_score' => 0,
                'warehouse_activity' => 'INACTIVE',
            ];

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

                return [
                    'batch_id'           => $row['batch_id'],
                    'product_id'         => $row['product_id'],
                    'product_name'       => $row['product_name'],
                    'warehouse_id'       => $row['warehouse_id'],
                    'warehouse_name'     => $row['warehouse_name'],
                    'category'           => $row['category'],
                    'warehouse_activity' => $warehouseActivity['warehouse_activity'],
                    'activity_score'     => $warehouseActivity['activity_score'],
                    'recommendation'     => $source ? 'TRANSFER_IN' : 'RESTOCK',
                    'suggested_qty'      => $source ? $suggestedQty : (int) max(($row['avg_daily_out'] * 30) - $row['current_quantity'], 0),
                    'from_warehouse'     => $source['warehouse_name'] ?? null,
                    'from_warehouse_id'  => $source['warehouse_id'] ?? null,
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

                return [
                    'batch_id'           => $row['batch_id'],
                    'product_id'         => $row['product_id'],
                    'product_name'       => $row['product_name'],
                    'warehouse_id'       => $row['warehouse_id'],
                    'warehouse_name'     => $row['warehouse_name'],
                    'category'           => $row['category'],
                    'warehouse_activity' => $warehouseActivity['warehouse_activity'],
                    'activity_score'     => $warehouseActivity['activity_score'],
                    'recommendation'     => $destination ? 'TRANSFER_OUT' : 'SLOW_MOVING_ALERT',
                    'suggested_qty'      => $destination ? $suggestedQty : 0,
                    'to_warehouse'       => $destination['warehouse_name'] ?? null,
                    'to_warehouse_id'    => $destination['warehouse_id'] ?? null,
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
                'recommendation'     => 'HOLD',
                'suggested_qty'      => 0,
                'from_warehouse'     => null,
                'to_warehouse'       => null,
            ];
        }, $this->analysisService->analyze($historyDays));
    }
}
