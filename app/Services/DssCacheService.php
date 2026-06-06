<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class DssCacheService
{
    public function storeAnalysis(int $days, array $data): void
    {
        Redis::set(
            "dss:analysis:{$days}",
            json_encode($data)
        );
    }

    public function storeRecommendation(int $days, array $data): void
    {
        Redis::set(
            "dss:recommendation:{$days}",
            json_encode($data)
        );
    }

    public function getAnalysis(int $days): array
    {
        return json_decode(
            Redis::get("dss:analysis:{$days}") ?? '[]',
            true
        );
    }

    public function getRecommendation(int $days): array
    {
        return json_decode(
            Redis::get("dss:recommendation:{$days}") ?? '[]',
            true
        );
    }
}
