<?php

namespace App\Services;

use App\Services\DssEngine; // ← fix namespace
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

    public function recommend(?int $historyDays = null): array // ← fix type hint
    {
        $historyDays     = $historyDays ?: config('dss.default_history_days');
        $recommendations = [];

        foreach ($this->analysisService->analyze($historyDays) as $row) {
            if ($row['category'] === 'FAST_MOVING') {
                // TODO: optimize - query engine dipanggil per row
                $source = $this->engine->findSlowMovingSource(
                    $row['product_id'],
                    $row['warehouse_id'],
                    $historyDays
                );

                $recommendations[] = [
                    'batch_id'       => $row['batch_id'],
                    'product_id'     => $row['product_id'],
                    'warehouse_id'   => $row['warehouse_id'],
                    'category'       => $row['category'],
                    'velocity'       => $row['velocity'],
                    'days_of_stock'  => $row['days_of_stock'],
                    'recommendation' => $source ? 'TRANSFER_IN' : 'RESTOCK',
                    'source'         => $source,
                ];
            } elseif ($row['category'] === 'SLOW_MOVING') {
                // TODO: optimize - query engine dipanggil per row
                $destination = $this->engine->findFastMovingDestination(
                    $row['product_id'],
                    $row['warehouse_id'],
                    $historyDays
                );

                $recommendations[] = [
                    'batch_id'       => $row['batch_id'],
                    'product_id'     => $row['product_id'],
                    'warehouse_id'   => $row['warehouse_id'],
                    'category'       => $row['category'],
                    'velocity'       => $row['velocity'],
                    'days_of_stock'  => $row['days_of_stock'],
                    'recommendation' => $destination ? 'TRANSFER_OUT' : 'SLOW_MOVING_ALERT',
                    'destination'    => $destination,
                ];
            } else {
                $recommendations[] = [
                    'batch_id'       => $row['batch_id'],
                    'product_id'     => $row['product_id'],
                    'warehouse_id'   => $row['warehouse_id'],
                    'category'       => $row['category'],
                    'velocity'       => $row['velocity'],
                    'days_of_stock'  => $row['days_of_stock'],
                    'recommendation' => 'HOLD',
                ];
            }
        }

        return $recommendations;
    }
}
