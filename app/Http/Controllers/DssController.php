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

    /**
     * POST /api/dss/push-distribution/preview
     */
    public function pushDistributionPreview(Request $request, \App\Services\DssRecommendationService $dssService, \App\Repositories\StockMutationRepository $mutationRepo): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string',
            'warehouse_id' => 'required|string',
            'store_ids' => 'required|array',
            'store_ids.*' => 'string',
            'total_available_stock' => 'required|integer|min:0',
            'is_urgent' => 'required|boolean',
        ]);

        $result = $dssService->calculatePushDistributionAllocation(
            $validated['product_id'],
            $validated['warehouse_id'],
            $validated['store_ids'],
            $validated['total_available_stock'],
            $validated['is_urgent'],
            $mutationRepo
        );

        return response()->json([
            'success' => true,
            'data'    => $result,
        ]);
    }

    /**
     * POST /api/dss/transfer-candidates
     * Get transfer candidates (SLOW_MOVING) for specific products from other warehouses
     */
    public function getTransferCandidates(Request $request): JsonResponse
    {
        $request->validate([
            'exclude_warehouse_id' => 'required|string',
            'product_ids' => 'required|array',
            'product_ids.*' => 'string'
        ]);

        $excludeWarehouseId = $request->input('exclude_warehouse_id');
        $productIds = $request->input('product_ids');
        $days = $this->resolveHistoryDays($request);
        
        $data = $this->cacheService->getAnalysis($days);

        $candidates = array_values(array_filter($data, function ($item) use ($excludeWarehouseId, $productIds) {
            return $item['category'] === 'SLOW_MOVING' 
                && $item['warehouse_id'] !== $excludeWarehouseId 
                && in_array($item['product_id'], $productIds);
        }));

        $candidates = array_slice($candidates, 0, 5);

        // Hybrid Optimization: Override snapshot stock with Real-Time Stock
        foreach ($candidates as &$candidate) {
            $realTimeStock = \App\Models\Batch::where('warehouse_id', $candidate['warehouse_id'])
                ->where('product_id', $candidate['product_id'])
                ->sum('current_quantity');
                
            $candidate['current_quantity'] = (int) $realTimeStock;
            $candidate['quantity'] = (int) $realTimeStock; // ensure fallback UI key is also updated
        }
        unset($candidate); // break reference

        // Filter out candidates that have completely run out of stock in real-time
        $candidates = array_values(array_filter($candidates, function($c) {
            return $c['current_quantity'] > 0;
        }));

        return response()->json([
            'success' => true,
            'data' => $candidates
        ]);
    }
}
