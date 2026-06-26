<?php

namespace App\Services;

use App\Models\Batch;
use App\Repositories\StockMutationRepository;
use InvalidArgumentException;

class StockAnalysisService
{
    protected StockMutationRepository $repository;

    public const CATEGORY_FAST_MOVING = 'FAST_MOVING';
    public const CATEGORY_NORMAL      = 'NORMAL';
    public const CATEGORY_SLOW_MOVING = 'SLOW_MOVING';

    public function __construct(StockMutationRepository $repository)
    {
        $this->repository = $repository;
    }

    public function analyze(?int $historyDays = null): array
    {
        $historyDays = $historyDays ?? 7;
        if ($historyDays <= 0) {
            throw new InvalidArgumentException("historyDays harus bernilai positif, diberikan: {$historyDays}.");
        }

        $thresholdFast = $this->resolveThreshold('dss.fast_moving_days_of_stock', 14);
        $thresholdSlow = $this->resolveThreshold('dss.slow_moving_days_of_stock', 60);

        $outboundMovements = $this->repository->getOutboundMovementByProductWarehouse($historyDays);

        $batches = Batch::with(['product', 'warehouse'])
            ->where('current_quantity', '>', 0)
            ->get()
            ->keyBy('id');

        $processedBatchIds = [];

        $results = $outboundMovements
            ->map(function ($row) use ($batches, $historyDays, $thresholdFast, $thresholdSlow, &$processedBatchIds) {
                $batch = $batches->get($row->batch_id);

                if (! $batch || ! $batch->warehouse) {
                    return null;
                }

                $processedBatchIds[] = $batch->id;

                $avgDailyOut = $this->calculateVelocity($row->total_out, $historyDays);
                $daysOfStock = $this->calculateDaysOfStock($batch->current_quantity, $avgDailyOut);
                $category = $this->resolveCategory($daysOfStock, $thresholdFast, $thresholdSlow);

                return [
                    'batch_id'         => $batch->id,
                    'product_id'       => $batch->product_id,
                    'product_name'     => $batch->product->name ?? 'Unknown',
                    'warehouse_id'     => $batch->warehouse_id,
                    'warehouse_name'   => $batch->warehouse->name ?? 'Unknown',
                    'current_quantity' => $batch->current_quantity,
                    'avg_daily_out'    => $avgDailyOut,
                    'days_of_stock'    => $daysOfStock,
                    'category'         => $category,
                    'price'            => $batch->price ?? 0,
                    'reference_days'   => $historyDays,
                    'expired_date'     => $batch->expired_date ? $batch->expired_date->format('Y-m-d') : null,
                ];
            })
            ->filter()
            ->values()
            ->toArray();

        $unmovedBatches = $batches->except($processedBatchIds);

        $unmovedResults = $unmovedBatches
            ->map(function (Batch $batch) use ($thresholdFast, $thresholdSlow, $historyDays) {
                return [
                    'batch_id'         => $batch->id,
                    'product_id'       => $batch->product_id,
                    'product_name'     => $batch->product->name ?? 'Unknown',
                    'warehouse_id'     => $batch->warehouse_id,
                    'warehouse_name'   => $batch->warehouse->name ?? 'Unknown',
                    'current_quantity' => $batch->current_quantity,
                    'avg_daily_out'    => 0.0,
                    'days_of_stock'    => null,
                    'category'         => $this->resolveCategory(null, $thresholdFast, $thresholdSlow),
                    'price'            => $batch->price ?? 0,
                    'reference_days'   => $historyDays,
                    'expired_date'     => $batch->expired_date ? $batch->expired_date->format('Y-m-d') : null,
                ];
            })
            ->values()
            ->toArray();

        return array_merge($results, $unmovedResults);
    }

    private function resolveThreshold(string $configKey, int $default): int
    {
        $value = config($configKey, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    private function calculateVelocity(float $totalQuantity, int $days): float
    {
        return round($totalQuantity / $days, 4);
    }

    private function calculateDaysOfStock(float $currentQuantity, float $velocity): ?float
    {
        if ($velocity <= 0) {
            return null;
        }
        return round($currentQuantity / $velocity, 1);
    }

    private function resolveCategory(?float $daysOfStock, int $fastThreshold, int $slowThreshold): string
    {
        if ($daysOfStock === null) {
            return self::CATEGORY_SLOW_MOVING;
        }

        if ($daysOfStock < $fastThreshold) {
            return self::CATEGORY_FAST_MOVING;
        }

        if ($daysOfStock > $slowThreshold) {
            return self::CATEGORY_SLOW_MOVING;
        }

        return self::CATEGORY_NORMAL;
    }
}
