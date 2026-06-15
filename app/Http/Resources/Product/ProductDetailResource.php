<?php

namespace App\Http\Resources\Product;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // 'id' => $this->id,
            // 'sku' => $this->sku,
            // 'name' => $this->name,
            // 'category' => [
            //     'id' => $this->category->id,
            //     'name' => $this->category->name,
            // ],
            // 'unit' => [
            //     'id' => $this->unit->id,
            //     'name' => $this->unit->name,
            //     'symbol' => $this->unit->symbol,
            // ],
            // 'supplier' => $this->productItems->map(function ($item) {
            //     return [
            //         'id' => $item->id,
            //         'supplier_id' => $item->supplier->id,
            //         'name' => $item->supplier->name,
            //         'unit_price' => $item->unit_price,
            //         'min_order_quantity' => $item->min_order_quantity,
            //         'lead_time_days' => $item->lead_time_days,
            //         'return_limit_days' => $item->return_limit_days,
            //         'is_preferred' => $item->is_preferred,
            //     ];
            // }),
        ];
    }
}
