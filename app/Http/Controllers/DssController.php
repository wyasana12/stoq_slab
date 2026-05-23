<?php

namespace App\Http\Controllers;

use App\Services\DssRecommendationService;
use App\Services\StockAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DssController extends Controller
{
    protected DssRecommendationService $recommendationService;
    protected StockAnalysisService $analysisService;

    public function __construct(
        DssRecommendationService $recommendationService,
        StockAnalysisService $analysisService
    ) {
        $this->recommendationService = $recommendationService;
        $this->analysisService       = $analysisService;
    }

    /**
     * GET /api/dss/analysis?days=30
     * Tampilkan hasil analisis velocity semua batch.
     */
    public function analysis(Request $request): JsonResponse
    {
        $days = $this->resolveHistoryDays($request);
       

        return response()->json([
            'success'      => true,
            'history_days' => $days,
            'summary' => [
                'total'       => count($data = $this->analysisService->analyze($days)),
                'fast_moving' => count(array_filter($data, fn($r) => $r['category'] === 'FAST_MOVING')),
                'slow_moving' => count(array_filter($data, fn($r) => $r['category'] === 'SLOW_MOVING')),
                'normal'      => count(array_filter($data, fn($r) => $r['category'] === 'NORMAL')),
            ],
            'data' => $data,
        ]);
    }

    /**
     * GET /api/dss/recommendations?days=30
     * Tampilkan rekomendasi DSS berdasarkan hasil analisis.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $days = $this->resolveHistoryDays($request);


        return response()->json([
            'success'      => true,
            'history_days' => $days,
            'summary' => [
                'total'             => count($data = $this->recommendationService->recommend($days)),
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
