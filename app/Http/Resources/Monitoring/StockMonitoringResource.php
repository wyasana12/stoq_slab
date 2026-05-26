<?php

namespace App\Http\Resources\Monitoring;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMonitoringResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'warehouse_id' => data_get($this->resource, 'warehouse_id'),
            'warehouse_name' => data_get($this->resource, 'warehouse_name'),
            'region_name' => data_get($this->resource, 'region_name'),
            'total_batches' => data_get($this->resource, 'total_batches', 0),
            'total_stock' => data_get($this->resource, 'total_stock', 0),
            'activity_count' => data_get($this->resource, 'activity_count', 0),
            'last_activity_at' => data_get($this->resource, 'last_activity_at'),
        ];
    }
}
