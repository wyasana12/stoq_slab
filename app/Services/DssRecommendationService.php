<?php

namespace App\Services;

use App\Services\DssEngine;
use App\Services\StockAnalysisService;

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
        $historyDays     = $historyDays ?: config('dss.default_history_days', 7);
        $recommendations = [];

        foreach ($this->analysisService->analyze($historyDays) as $row) {
            if ($row['category'] === 'FAST_MOVING') {
                $source = $this->engine->findSlowMovingSource(
                    $row['product_id'],
                    $row['warehouse_id'],
                    $historyDays
                );

                $recommendations[] = [
                    'batch_id'       => $row['batch_id'],
                    'product_id'     => $row['product_id'],
                    'product_name'   => $row['product_name'] ?? 'Unknown',
                    'warehouse_id'   => $row['warehouse_id'],
                    'warehouse_name' => $row['warehouse_name'] ?? 'Unknown',
                    'category'       => $row['category'],
                    // Mengubah 'velocity' menjadi 'velocity_per_day'
                    'velocity'       => $row['velocity_per_day'],
                    'days_of_stock'  => $row['days_of_stock'],
                    'recommendation' => $source ? 'TRANSFER_IN' : 'RESTOCK',
                    'source'         => $source,
                ];
            } elseif ($row['category'] === 'SLOW_MOVING') {
                $destination = $this->engine->findFastMovingDestination(
                    $row['product_id'],
                    $row['warehouse_id'],
                    $historyDays
                );

                $recommendations[] = [
                    'batch_id'       => $row['batch_id'],
                    'product_id'     => $row['product_id'],
                    'product_name'   => $row['product_name'] ?? 'Unknown',
                    'warehouse_id'   => $row['warehouse_id'],
                    'warehouse_name' => $row['warehouse_name'] ?? 'Unknown',
                    'category'       => $row['category'],
                    // PERBAIKAN: Mengubah 'velocity' menjadi 'velocity_per_day'
                    'velocity'       => $row['velocity_per_day'],
                    'days_of_stock'  => $row['days_of_stock'],
                    'recommendation' => $destination ? 'TRANSFER_OUT' : 'SLOW_MOVING_ALERT',
                    'destination'    => $destination,
                ];
            } else {
                $recommendations[] = [
                    'batch_id'       => $row['batch_id'],
                    'product_id'     => $row['product_id'],
                    'product_name'   => $row['product_name'] ?? 'Unknown',
                    'warehouse_id'   => $row['warehouse_id'],
                    'warehouse_name' => $row['warehouse_name'] ?? 'Unknown',
                    'category'       => $row['category'],
                    // PERBAIKAN: Mengubah 'velocity' menjadi 'velocity_per_day'
                    'velocity'       => $row['velocity_per_day'],
                    'days_of_stock'  => $row['days_of_stock'],
                    'recommendation' => 'HOLD',
                ];
            }
        }

        return $recommendations;
    }
}
