<?php

namespace App\Services;

use App\Models\Batch;
use App\Repositories\StockMutationRepository;
use InvalidArgumentException;

class StockAnalysisService
{
    /**
     * Engine analisa mendeteksi repository secara eksplisit di sini.
     */
    protected StockMutationRepository $repository;

    /**
     * Kategori klasifikasi stok berdasarkan alur DSS dasar.
     */
    public const CATEGORY_FAST_MOVING = 'FAST_MOVING';
    public const CATEGORY_NORMAL      = 'NORMAL'; // Jika nilai persis sama (Day 8 == Rata-rata)
    public const CATEGORY_SLOW_MOVING = 'SLOW_MOVING';
    public const CATEGORY_DEAD_STOCK  = 'DEAD_STOCK';

    public function __construct(StockMutationRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Analisis utama: klasifikasi pergerakan stok per batch per warehouse.
     * Menggunakan komparasi rata-rata historis (Day 1 - Day 7) vs aktual harian (Day 8).
     *
     * @param int|null $historyDays Periode lookback untuk rata-rata (Default: 7 hari).
     * @param bool     $withTrend   Aktifkan analisis trend (opsional).
     *
     * @throws InvalidArgumentException Jika historyDays bernilai negatif atau nol.
     */
    public function analyze(?int $historyDays = null, bool $withTrend = false): array
    {
        $historyDays = $historyDays ?? 7;
        if ($historyDays <= 0) {
            throw new InvalidArgumentException("historyDays harus bernilai positif, diberikan: {$historyDays}.");
        }

        $thresholdFast = $this->resolveThreshold('dss.fast_moving_days_of_stock', 14);
        $thresholdSlow = $this->resolveThreshold('dss.slow_moving_days_of_stock', 60);

        /**
         * Repository instance (typed as mixed to accommodate dynamic proxy/implementation
         * used at runtime and to satisfy static analyzers when the concrete method
         * is not declared on the base repository class).
         * @var mixed $repo
         */
        $repo = $this->repository;

        // 2. Ambil data rata-rata historis (7 hari lalu) & data aktual hari ke-8 (Hari Ini)
        $historicalMovements = $repo->getOutboundVelocityByBatchWarehouse($historyDays);
        $day8Movements       = $repo->getActualDay8Movement()->keyBy(fn($r) => $r->batch_id . '_' . $r->warehouse_id);

        $batchIds = $historicalMovements->pluck('batch_id')->unique()->all();

        // Eager load batch + relasi; hanya ambil yang masih ada stok
        $batches = Batch::with(['product', 'warehouse'])
            ->whereIn('id', $batchIds)
            ->where('current_quantity', '>', 0)
            ->get()
            ->keyBy('id');

        // Perhitungan short-term velocity untuk analisis trend (Opsional)
        $shortTermVelocities = collect();
        if ($withTrend && $historyDays > 7) {
            $shortTermDays = max(7, (int) round($historyDays / 4));
            $shortTermVelocities = $repo
                ->getOutboundVelocityByBatchWarehouse($shortTermDays)
                ->keyBy(fn($r) => $r->batch_id . '_' . $r->warehouse_id);
        }

        $results = $historicalMovements
            ->map(function ($row) use (
                $batches,
                $historyDays,
                $day8Movements,
                $withTrend,
                $shortTermVelocities
            ): ?array {
                $batch = $batches->get($row->batch_id);

                // Batch tidak ditemukan atau stok habis di gudang ini — lewati
                if (! $batch || ! $batch->warehouse) {
                    return null;
                }

                // 3. Hitung Rata-rata pergerakan harian 7 hari sebelumnya (A)
                $averageVelocity = $this->calculateVelocity($row->total_quantity, $historyDays);

                // 4. Ambil pergerakan aktual volume pada hari ke-8 / hari ini (B)
                $key = $row->batch_id . '_' . $batch->warehouse_id;
                $day8ActualVolume = $day8Movements->has($key) ? (float) $day8Movements->get($key)->total_quantity : 0.0;

                // 5. PENENTUAN KATEGORI (Inti Alur DSS Kamu)
                // Membandingkan Aktual Hari ke-8 dengan Rata-rata 7 Hari Sebelumnya
                if ($day8ActualVolume > $averageVelocity) {
                    $category = self::CATEGORY_FAST_MOVING;
                } elseif ($day8ActualVolume < $averageVelocity) {
                    $category = self::CATEGORY_SLOW_MOVING;
                } else {
                    $category = self::CATEGORY_NORMAL;
                }

                // Days of stock tetap dihitung menggunakan rata-rata historis sebagai data informasi pelengkap
                $daysOfStock = $this->calculateDaysOfStock($batch->current_quantity, $averageVelocity);

                $result = [
                    'batch_id'              => $row->batch_id,
                    'warehouse_id'          => $batch->warehouse_id,
                    'warehouse_name'        => $batch->warehouse->name ?? 'Gudang Tidak Terdaftar',
                    'mutation_warehouse_id' => $row->warehouse_id,
                    'product_id'            => $batch->product_id,
                    'product_name'          => $batch->product->name ?? 'Produk Tidak Terdaftar',
                    'current_quantity'      => $batch->current_quantity,
                    'velocity_per_day'      => $averageVelocity,
                    'day_8_actual_volume'   => $day8ActualVolume,
                    'days_of_stock'         => $daysOfStock,
                    'category'              => $category,
                    'reference_days'        => $historyDays,
                    'transaction_count'     => $row->transaction_count ?? 0,
                ];

                if ($withTrend) {
                    $trendKey        = $row->batch_id . '_' . $row->warehouse_id;
                    $shortTermRow    = $shortTermVelocities->get($trendKey);
                    $result['trend'] = $this->resolveTrend($averageVelocity, $shortTermRow, $historyDays);
                }

                return $result;
            })
            ->filter()
            ->values()
            ->toArray();

        // 6. Ambil data batch yang sama sekali tidak memiliki riwayat mutasi selama 7 hari terakhir (DEAD_STOCK)
        $deadStockResults = $this->findDeadStockBatches($batchIds);

        return array_merge($results, $deadStockResults);
    }

    /**
     * Analisis berdasarkan satu spesifik produk (digunakan untuk sub-fitur atau widget detail)
     */
    public function analyzeByProduct(string $productId, ?int $historyDays = null): array
    {
        $historyDays   = $historyDays ?? 7;
        $thresholdFast = $this->resolveThreshold('dss.fast_moving_days_of_stock', 14);
        $thresholdSlow = $this->resolveThreshold('dss.slow_moving_days_of_stock', 60);

        /** @var \App\Repositories\StockMutationRepository $repo */
        $repo = $this->repository;
        $velocities = $repo->getVelocityByProductWarehouse($productId, $historyDays);

        $stockByWarehouse = Batch::where('product_id', $productId)
            ->where('current_quantity', '>', 0)
            ->selectRaw('warehouse_id, SUM(current_quantity) as total_quantity')
            ->groupBy('warehouse_id')
            ->pluck('total_quantity', 'warehouse_id');

        return $velocities
            ->map(function ($row) use ($historyDays, $thresholdFast, $thresholdSlow, $stockByWarehouse): ?array {
                $currentStock = $stockByWarehouse->get($row->warehouse_id, 0);
                $velocity     = $this->calculateVelocity($row->total_quantity, $historyDays);
                $daysOfStock  = $this->calculateDaysOfStock($currentStock, $velocity);

                $category     = $this->resolveCategory($daysOfStock, $thresholdFast, $thresholdSlow);

                return [
                    'warehouse_id'     => $row->warehouse_id,
                    'current_quantity' => $currentStock,
                    'velocity_per_day' => $velocity,
                    'days_of_stock'    => $daysOfStock,
                    'category'         => $category,
                    'reference_days'   => $historyDays,
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    private function resolveThreshold(string $configKey, int $default): int
    {
        $value = config($configKey, $default);
        if ($value === null || ! is_numeric($value)) {
            return $default;
        }
        return (int) $value;
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

    /**
     * Fallback resolver kategori jika days of stock dibutuhkan secara independen
     */
    private function resolveCategory(?float $daysOfStock, int $fastThreshold, int $slowThreshold): string
    {
        if ($daysOfStock === null) {
            return self::CATEGORY_DEAD_STOCK;
        }
        if ($daysOfStock < $fastThreshold) {
            return self::CATEGORY_FAST_MOVING;
        }
        if ($daysOfStock > $slowThreshold) {
            return self::CATEGORY_SLOW_MOVING;
        }
        return self::CATEGORY_NORMAL;
    }

    private function resolveTrend(float $longTermVelocity, ?object $shortTermRow, int $longTermDays): string
    {
        if (! $shortTermRow || $longTermVelocity <= 0) {
            return 'STABLE';
        }

        $shortTermDays     = max(7, (int) round($longTermDays / 4));
        $shortTermVelocity = $this->calculateVelocity($shortTermRow->total_quantity, $shortTermDays);
        $ratio             = $shortTermVelocity / $longTermVelocity;

        if ($ratio > 1.2) {
            return 'INCREASING';
        }
        if ($ratio < 0.8) {
            return 'DECREASING';
        }
        return 'STABLE';
    }

    private function findDeadStockBatches(array $batchIdsWithMovement): array
    {
        return Batch::with(['product', 'warehouse'])
            ->where('current_quantity', '>', 0)
            ->whereNotIn('id', $batchIdsWithMovement)
            ->get()
            ->map(function (Batch $batch): array {
                return [
                    'batch_id'              => $batch->id,
                    'warehouse_id'          => $batch->warehouse_id,
                    'warehouse_name'        => $batch->warehouse->name ?? 'Gudang Tidak Terdaftar',
                    'mutation_warehouse_id' => $batch->warehouse_id,
                    'product_id'            => $batch->product_id,
                    'product_name'          => $batch->product->name ?? 'Produk Tidak Terdaftar',
                    'current_quantity'      => $batch->current_quantity,
                    'velocity_per_day'      => 0.0,
                    'day_8_actual_volume'   => 0.0,
                    'days_of_stock'         => null,
                    'category'              => self::CATEGORY_DEAD_STOCK,
                    'reference_days'        => 0,
                    'transaction_count'     => 0,
                    'trend'                 => 'STABLE',
                ];
            })
            ->toArray();
    }
}
