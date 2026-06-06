<?php

namespace App\Jobs;

use App\Services\DssCacheService;
use App\Services\DssRecommendationService;
use App\Services\StockAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDssCacheJob implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;
    use SerializesModels;

    public int $timeout = 600;
    public int $tries = 3;

    public function handle(StockAnalysisService $analystService, DssRecommendationService $recommendationService, DssCacheService $cacheService): void
    {
        foreach (config('dss.history_days', [7, 30, 60, 90]) as $days) {
            $analyst = $analystService->analyze($days);

            $cacheService->storeAnalysis($days, $analyst);

            Log::info('Analysis saved', [
                'days' => $days,
            ]);


            $recommendation = $recommendationService->recommend($days);

            $cacheService->storeRecommendation($days, $recommendation);

            Log::info('Recommendation saved', [
                'days' => $days,
            ]);
        }
    }
}
