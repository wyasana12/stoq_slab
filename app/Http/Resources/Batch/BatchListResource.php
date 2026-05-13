<?php

namespace App\Http\Resources\Batch;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchListResource extends JsonResource
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
            'batch_code' => $this->batch_code,
            'product' => [
                'id' => $this->product?->id ?? 'N/A',
                'name' => $this->product?->name ?? 'N/A',
                'current_quantity' => $this->current_quantity,
                'price' => $this->price,
                'expired_date' => $this->expired_date?->format('l, d F Y') ?? 'N/A',
            ],
            'warehouse' => [
                'id' => $this->warehouse?->id ?? 'N/A',
                'name' => $this->warehouse?->name ?? 'N/A',
            ],
            'created_at' => $this->created_at?->format('l, d F Y') ?? 'N/A',
            'updated_at' => $this->updated_at?->format('l, d F Y') ?? 'N/A'
        ];
    }
}
