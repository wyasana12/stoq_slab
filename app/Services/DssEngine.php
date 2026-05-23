<?php

namespace App\Services;

use App\Repositories\StockMutationRepository;

class DssEngine
{
    protected StockMutationRepository $repository;

    public function __construct(StockMutationRepository $repository)
    {
        $this->repository = $repository;
    }

    public function findSlowMovingSource(string $productId, string $destinationWarehouseId, int $historyDays = null): ?array
    {
        $historyDays = $historyDays ?: config('dss.default_history_days');
        $thresholdSlow = config('dss.slow_moving_days_of_stock');

        $candidateBatches = $this->repository
            ->getBatchesByProductExcludingWarehouse($productId, $destinationWarehouseId);

        foreach ($candidateBatches as $batch) {
            $velocityByWarehouse = $this->repository
                ->getVelocityByProductWarehouse($productId, $historyDays)
                ->firstWhere('warehouse_id', $batch->warehouse_id);

            $velocity = $velocityByWarehouse ? round($velocityByWarehouse->total_quantity / $historyDays, 2) : 0;
            $daysOfStock = $velocity > 0 ? round($batch->current_quantity / $velocity, 1) : null;

            if ($daysOfStock === null || $daysOfStock > $thresholdSlow) {
                return [
                    'source_warehouse_id' => $batch->warehouse_id,
                    'source_warehouse_name' => $batch->warehouse->name,
                    'batch_id' => $batch->id,
                    'current_quantity' => $batch->current_quantity,
                    'days_of_stock' => $daysOfStock,
                ];
            }
        }

        return null;
    }

    public function findFastMovingDestination(string $productId, string $sourceWarehouseId, int $historyDays = null): ?array
    {
        $historyDays = $historyDays ?: config('dss.default_history_days');
        $thresholdFast = config('dss.fast_moving_days_of_stock');

        $candidateBatches = $this->repository
            ->getBatchesByProductExcludingWarehouse($productId, $sourceWarehouseId);

        foreach ($candidateBatches as $batch) {
            $velocityByWarehouse = $this->repository
                ->getVelocityByProductWarehouse($productId, $historyDays)
                ->firstWhere('warehouse_id', $batch->warehouse_id);

            $velocity = $velocityByWarehouse ? round($velocityByWarehouse->total_quantity / $historyDays, 2) : 0;
            $daysOfStock = $velocity > 0 ? round($batch->current_quantity / $velocity, 1) : null;

            if ($daysOfStock !== null && $daysOfStock < $thresholdFast) {
                return [
                    'destination_warehouse_id' => $batch->warehouse_id,
                    'destination_warehouse_name' => $batch->warehouse->name,
                    'batch_id' => $batch->id,
                    'current_quantity' => $batch->current_quantity,
                    'days_of_stock' => $daysOfStock,
                ];
            }
        }

        return null;
    }
}
