<?php

namespace App\Http\Resources\Batch;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchDetailResource extends JsonResource
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
                'initial_quantity' => $this->initial_quantity,
                'current_quantity' => $this->current_quantity,
                'price' => $this->price,
                'condition' => $this->condition,
                'rack_location' => $this->rack_location ?? 'N/A',
                'production_date' => $this->production_date?->format('l, d F Y') ?? 'N/A',
                'expired_date' => $this->expired_date?->format('l, d F Y') ?? 'N/A',
            ],
            'supplier' => [
                'id' => $this->receive?->purchase?->supplier->id ?? 'N/A',
                'name' => $this->receive?->purchase?->supplier->name ?? 'N/A'
            ],
            'barcode' => $this->barcode ? asset('storage/' . $this->barcode) : 'N/A',
        ];
    }
}
