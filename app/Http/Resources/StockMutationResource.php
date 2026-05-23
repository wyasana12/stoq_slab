<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMutationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'warehouse_id' => $this->warehouse?->name,
            'batch_id' => $this->batch?->batch_code,
            'change_quantity' => $this->change_quantity,
            'before_quantity' => $this->before_quantity,
            'after_quantity' => $this->after_quantity,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'notes' => $this->notes,
            'status' => $this->status,
        ];
    }
}
