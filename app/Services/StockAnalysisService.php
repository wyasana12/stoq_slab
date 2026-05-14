<?php

namespace App\Services;

use App\Models\Batch;
use App\Repositories\StockMutationRepository;

class StockAnalysisService
{
    protected StockMutationRepository $repository;

    public function __construct(StockMutationRepository $repository)
    {
        $this->repository = $repository;
    }

    public function analyze(?int $historyDays = null): array
    {
        $historyDays   = $historyDays ?: config('dss.default_history_days');
        $thresholdFast = config('dss.fast_moving_days_of_stock');
        $thresholdSlow = config('dss.slow_moving_days_of_stock');

        $velocities = $this->repository->getVelocityByBatchWarehouse($historyDays);
        $batchIds   = $velocities->pluck('batch_id')->unique()->all();

        $batches = Batch::with(['product', 'warehouse'])
            ->whereIn('id', $batchIds)
            ->where('current_quantity', '>', 0) // skip batch kosong
            ->get()
            ->keyBy('id');

        return $velocities->map(function ($row) use ($batches, $historyDays, $thresholdFast, $thresholdSlow) {
            $batch = $batches->get($row->batch_id);

            // Skip jika batch tidak ditemukan (soft deleted, dll)
            if (!$batch) return null;

            $velocity    = $historyDays > 0 ? round($row->total_quantity / $historyDays, 2) : 0;
            $daysOfStock = $velocity > 0 ? round($batch->current_quantity / $velocity, 1) : null;
            $category    = $this->resolveCategory($daysOfStock, $thresholdFast, $thresholdSlow);

            return [
                'batch_id'         => $row->batch_id,
                'warehouse_id'     => $row->warehouse_id,
                'product_id'       => $batch->product_id,
                'product_name'     => $batch->product->name,
                'warehouse_name'   => $batch->warehouse->name,
                'current_quantity' => $batch->current_quantity,
                'velocity'         => $velocity,
                'days_of_stock'    => $daysOfStock,
                'category'         => $category,
                'reference_days'   => $historyDays,
            ];
        })
            ->filter()   // buang null
            ->values()   // reset index
            ->toArray();
    }

    private function resolveCategory(?float $daysOfStock, int $fastThreshold, int $slowThreshold): string
    {
        if ($daysOfStock === null) {
            return 'SLOW_MOVING';
        }

        if ($daysOfStock < $fastThreshold) {
            return 'FAST_MOVING';
        }

        if ($daysOfStock > $slowThreshold) {
            return 'SLOW_MOVING';
        }

        return 'NORMAL';
    }
}
