<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product?->id,
            'sku' => $this->product?->sku,
            'name' => $this->product?->name,
            'category' =>
            [
                'id' => $this->product?->category?->id,
                'name' => $this->product?->category?->name,
            ],
            'unit' => [
                'id' => $this->product?->unit?->id,
                'symbol' => $this->product?->unit?->symbol,
            ],
            'supplier' => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
                'unit_price' => (float) $this->unit_price,
            ],
        ];
    }
}
