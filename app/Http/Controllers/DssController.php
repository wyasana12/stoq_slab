<?php

namespace App\Http\Controllers;

use App\Services\DssCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DssController extends Controller
{
    protected DssCacheService $cacheService;

    public function __construct(
        DssCacheService $cacheService
    ) {
        $this->cacheService = $cacheService;
    }

    /**
     * GET /api/dss/analysis?days=30&warehouse_id=xxx
     * Tampilkan hasil analisis velocity semua batch, bisa difilter per gudang.
     */
    public function analysis(Request $request): JsonResponse
    {
        $days = $this->resolveHistoryDays($request);

        $warehouseId = $request->query('warehouse_id');
       
        $userWarehouseId = $request->user()?->warehouse_id;
        if ($userWarehouseId) {
            $warehouseId = $userWarehouseId;
        }

        $data = $this->cacheService->getAnalysis($days);

        if ($warehouseId) {
            $data = array_values(array_filter($data, fn($r) => $r['warehouse_id'] == $warehouseId));
        }

        return response()->json([
            'success'      => true,
            'history_days' => $days,
            'warehouse_id' => $warehouseId,
            'summary' => [
                'total'       => count($data),
                'fast_moving' => count(array_filter($data, fn($r) => $r['category'] === 'FAST_MOVING')),
                'slow_moving' => count(array_filter($data, fn($r) => $r['category'] === 'SLOW_MOVING')),
                'normal'      => count(array_filter($data, fn($r) => $r['category'] === 'NORMAL')),
            ],
            'data' => $data,
        ]);
    }

    /**
     * GET /api/dss/recommendations?days=30&warehouse_id=xxx
     * Tampilkan rekomendasi DSS berdasarkan hasil analisis, bisa difilter per gudang.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $days = $this->resolveHistoryDays($request);
        $warehouseId = $request->query('warehouse_id');

        // Auto-filter to assigned warehouse for admin/staff users
        $userWarehouseId = $request->user()?->warehouse_id;
        if ($userWarehouseId) {
            $warehouseId = $userWarehouseId;
        }
      
        $data = $this->cacheService->getRecommendation($days);

        if ($warehouseId) {
            $data = array_values(array_filter($data, fn($r) => $r['warehouse_id'] == $warehouseId));
        }

        return response()->json([
            'success'      => true,
            'history_days' => $days,
            'warehouse_id' => $warehouseId,
            'summary' => [
                'total'             => count($data),
                'restock'           => count(array_filter($data, fn($r) => $r['recommendation'] === 'RESTOCK')),
                'transfer_in'       => count(array_filter($data, fn($r) => $r['recommendation'] === 'TRANSFER_IN')),
                'transfer_out'      => count(array_filter($data, fn($r) => $r['recommendation'] === 'TRANSFER_OUT')),
                'slow_moving_alert' => count(array_filter($data, fn($r) => $r['recommendation'] === 'SLOW_MOVING_ALERT')),
                'hold'              => count(array_filter($data, fn($r) => $r['recommendation'] === 'HOLD')),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Resolve history days dari query param.
     * Fallback ke default config jika tidak valid.
     */
    private function resolveHistoryDays(Request $request): int
    {
        $days        = (int) $request->query('days', config('dss.default_history_days'));
        $allowedDays = config('dss.history_days', [30, 60, 90]);

        return in_array($days, $allowedDays)
            ? $days
            : config('dss.default_history_days');
    }
}
