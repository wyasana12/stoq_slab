<?php

namespace App\Http\Resources\Monitoring;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'batch_id' => data_get($this->resource, 'batch_id'),
            'batch_code' => data_get($this->resource, 'batch_code'),
            'warehouse_id' => data_get($this->resource, 'warehouse_id'),
            'warehouse_name' => data_get($this->resource, 'warehouse_name'),
            'region_name' => data_get($this->resource, 'region_name'),
            'product_id' => data_get($this->resource, 'product_id'),
            'product_name' => data_get($this->resource, 'product_name'),
            'current_quantity' => data_get($this->resource, 'current_quantity', 0),
            'production_date' => data_get($this->resource, 'production_date'),
            'expired_date' => data_get($this->resource, 'expired_date'),
            'created_at' => data_get($this->resource, 'created_at'),
            'updated_at' => data_get($this->resource, 'updated_at'),
        ];
    }
}
