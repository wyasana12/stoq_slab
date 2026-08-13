<?php

namespace App\Http\Resources\ProductSupplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductSupplierListResource extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->groupBy(function ($item) {
            return $item->suppliers?->id;
        })->map(function ($items, $supplierId) {
            $supplier = $items->first()->suppliers;

            return [
                'id' => $supplierId,
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'supplier_code' => $supplier->supplier_code,
                    'status' => $supplier->status,
                ],
                'items' => $items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'SPN' => $item->SPN ?? '-',
                        'unit_price' => $item->unit_price,
                        'min_order_quantity' => $item->min_order_quantity,
                        'lead_time_days' => $item->lead_time_days,
                        'return_limit_days' => $item->return_limit_days,
                        'is_preferred' => $item->is_preferred,
                        'product' => [
                            'id' => $item->products?->id,
                            'sku' => $item->products?->sku,
                            'name' => $item->products?->name,
                        ],
                    ];
                })->values()->all(),
            ];
        })->values()->all();
    }
}
