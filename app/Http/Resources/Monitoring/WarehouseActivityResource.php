<?php

namespace App\Http\Resources\Monitoring;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WarehouseActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this->resource, 'id'),
            'activity_type' => data_get($this->resource, 'activity_type'),
            'title' => data_get($this->resource, 'title'),
            'warehouse_id' => data_get($this->resource, 'warehouse_id'),
            'warehouse_name' => data_get($this->resource, 'warehouse_name'),
            'batch_id' => data_get($this->resource, 'batch_id'),
            'batch_code' => data_get($this->resource, 'batch_code'),
            'product_id' => data_get($this->resource, 'product_id'),
            'product_name' => data_get($this->resource, 'product_name'),
            'status' => data_get($this->resource, 'status'),
            'quantity' => data_get($this->resource, 'quantity', 0),
            'before_quantity' => data_get($this->resource, 'before_quantity'),
            'after_quantity' => data_get($this->resource, 'after_quantity'),
            'reference_type' => data_get($this->resource, 'reference_type'),
            'reference_id' => data_get($this->resource, 'reference_id'),
            'notes' => data_get($this->resource, 'notes'),
            'activity_at' => data_get($this->resource, 'activity_at'),
            'payload' => data_get($this->resource, 'payload', []),
        ];
    }
}
