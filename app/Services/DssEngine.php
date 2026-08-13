<?php

namespace App\Services;

use App\Repositories\StockMutationRepository;
use Illuminate\Support\Collection;

class DssEngine
{
    protected StockMutationRepository $repository;

    public function __construct(StockMutationRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getWarehouseActivities(int $historyDays): array
    {
        $movements = $this->repository->getWarehouseTotalMovement($historyDays);
        $values = $movements->pluck('total_movement')->sort()->values();
        $median = $this->calculateMedian($values);

        return $movements
            ->mapWithKeys(function (object $row) use ($median) {
                $score = $this->calculateActivityScore($row->total_movement, $median);

                return [
                    $row->warehouse_id => [
                        'warehouse_id'       => $row->warehouse_id,
                        'total_movement'     => (float) $row->total_movement,
                        'activity_score'     => $score,
                        'warehouse_activity' => $this->resolveWarehouseActivity($score),
                    ],
                ];
            })
            ->toArray();
    }

    public function findSlowMovingSource(
        string $productId,
        string $destinationWarehouseId,
        int $historyDays,
        array $warehouseActivities
    ): ?array {
        $velocities = $this->repository
            ->getVelocityByProductWarehouse($productId, $historyDays)
            ->keyBy('warehouse_id');

        return collect($this->repository->getBatchesByProductExcludingWarehouse($productId, $destinationWarehouseId))
            ->map(function (object $batch) use ($velocities, $historyDays, $warehouseActivities) {
                $velocityRow = $velocities->get($batch->warehouse_id);
                $avgDailyOut = $velocityRow ? round($velocityRow->total_quantity / $historyDays, 4) : 0.0;
                $daysOfStock = $this->calculateDaysOfStock($batch->current_quantity, $avgDailyOut);

                return [
                    'warehouse_id'       => $batch->warehouse_id,
                    'warehouse_name'     => $batch->warehouse?->name,
                    'batch_id'           => $batch->id,
                    'current_quantity'   => $batch->current_quantity,
                    'avg_daily_out'      => $avgDailyOut,
                    'days_of_stock'      => $daysOfStock,
                    'category'           => $this->resolveBatchCategory($daysOfStock),
                    'activity_score'     => $warehouseActivities[$batch->warehouse_id]['activity_score'] ?? 0,
                    'warehouse_activity' => $warehouseActivities[$batch->warehouse_id]['warehouse_activity'] ?? 'INACTIVE',
                ];
            })
            ->filter(fn($row) => $row['category'] === StockAnalysisService::CATEGORY_SLOW_MOVING)
            ->sortByDesc(fn($row) => $row['activity_score'])
            ->values()
            ->first();
    }

    public function findFastMovingDestination(
        string $productId,
        string $sourceWarehouseId,
        int $historyDays,
        array $warehouseActivities
    ): ?array {
        $velocities = $this->repository
            ->getVelocityByProductWarehouse($productId, $historyDays)
            ->keyBy('warehouse_id');

        return collect($this->repository->getBatchesByProductExcludingWarehouse($productId, $sourceWarehouseId))
            ->map(function (object $batch) use ($velocities, $historyDays, $warehouseActivities) {
                $velocityRow = $velocities->get($batch->warehouse_id);
                $avgDailyOut = $velocityRow ? round($velocityRow->total_quantity / $historyDays, 4) : 0.0;
                $daysOfStock = $this->calculateDaysOfStock($batch->current_quantity, $avgDailyOut);

                return [
                    'warehouse_id'       => $batch->warehouse_id,
                    'warehouse_name'     => $batch->warehouse?->name,
                    'batch_id'           => $batch->id,
                    'current_quantity'   => $batch->current_quantity,
                    'avg_daily_out'      => $avgDailyOut,
                    'days_of_stock'      => $daysOfStock,
                    'category'           => $this->resolveBatchCategory($daysOfStock),
                    'activity_score'     => $warehouseActivities[$batch->warehouse_id]['activity_score'] ?? 0,
                    'warehouse_activity' => $warehouseActivities[$batch->warehouse_id]['warehouse_activity'] ?? 'INACTIVE',
                ];
            })
            ->filter(fn($row) => $row['category'] === StockAnalysisService::CATEGORY_FAST_MOVING)
            ->sortByDesc(fn($row) => $row['activity_score'])
            ->values()
            ->first();
    }

    private function calculateMedian(Collection $values): float
    {
        $count = $values->count();
        if ($count === 0) {
            return 0.0;
        }

        if ($count % 2 === 1) {
            return (float) $values->get(intdiv($count, 2));
        }

        $lower = (float) $values->get($count / 2 - 1);
        $upper = (float) $values->get($count / 2);

        return ($lower + $upper) / 2;
    }

    private function calculateActivityScore(float $totalMovement, float $median): float
    {
        if ($median <= 0) {
            return $totalMovement > 0 ? 1000.0 : 0.0;
        }

        return round($totalMovement / $median * 100, 2);
    }

    private function resolveWarehouseActivity(float $score): string
    {
        $activeThreshold = config('dss.warehouse_activity.active_threshold', 150);
        $inactiveThreshold = config('dss.warehouse_activity.inactive_threshold', 75);

        if ($score > $activeThreshold) {
            return 'ACTIVE';
        }

        if ($score < $inactiveThreshold) {
            return 'INACTIVE';
        }

        return 'MODERATE';
    }

    private function calculateDaysOfStock(float $currentQuantity, float $velocity): ?float
    {
        if ($velocity <= 0) {
            return null;
        }

        return round($currentQuantity / $velocity, 1);
    }

    private function resolveBatchCategory(?float $daysOfStock): string
    {
        $thresholdFast = config('dss.fast_moving_days_of_stock', 14);
        $thresholdSlow = config('dss.slow_moving_days_of_stock', 60);

        if ($daysOfStock === null) {
            return StockAnalysisService::CATEGORY_SLOW_MOVING;
        }

        if ($daysOfStock < $thresholdFast) {
            return StockAnalysisService::CATEGORY_FAST_MOVING;
        }

        if ($daysOfStock > $thresholdSlow) {
            return StockAnalysisService::CATEGORY_SLOW_MOVING;
        }

        return StockAnalysisService::CATEGORY_NORMAL;
    }
}
