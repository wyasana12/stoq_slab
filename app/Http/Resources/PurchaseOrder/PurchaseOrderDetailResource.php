<?php

namespace App\Http\Resources\PurchaseOrder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderDetailResource extends JsonResource
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
            'po_code' => $this->po_code,
            'supplier' => [
                'id' => $this->supplier?->id,
                'name' => $this->supplier?->name,
            ],
            'warehouse' => [
                'id' => $this->warehouse?->id,
                'name' => $this->warehouse?->name,
            ],
            'products' => $this->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->product->name,
                    'quantity_ordered' => $item->quantity_ordered,
                    'quantity_received' => $item->quantity_received,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ];
            }),
            'total_amount' => $this->total_amount,
            'created' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
            ],
            'approved_at' => $this->approved_at?->format('l, d F Y'),
            'order_date' =>  $this->order_date?->format('l, d F Y'),
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}