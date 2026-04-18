<?php

namespace App\Http\Resources\Receive;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiveDetailResource extends JsonResource
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
            'receiving_code' => $this->receiving_code,
            'warehouse' => [
                'id' => $this->purchase->warehouse->id ?? 'N/A',
                'name' => $this->purchase->warehouse->name ?? 'N/A',
            ],
            'purchase' => [
                'id' => $this->purchase->id ?? 'N/A',
                'po_code' => $this->purchase->po_code ?? 'N/A',
                'order_date' => $this->purchase->order_date?->format('l, d F Y') ?? 'N/A',
            ],
            'products' => $this->items->map(function ($i) {
                return [
                    'id' => $i->products->id,
                    'name' => $i->products->name,
                    'quantity_accepted' => $i->quantity_accepted,
                    'quantity_rejected' => $i->quantity_rejected,
                    'notes' => $i->notes,
                ];
            }),
            'receiving' => [
                'id' => $this->user->id ?? 'N/A',
                'name' => $this->user->name ?? 'N/A',
                'date' => $this->receiving_date?->format('l, d F Y') ?? 'N/A',
            ],
            'status' => $this->status,
            'created_at' => $this->created_at?->format('l, d F Y') ?? 'N/A',
            'updated_at' => $this->updated_at?->format('l, d F Y') ?? 'N/A',
        ];
    }
}
