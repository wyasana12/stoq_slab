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
                'id' => $this->warehouse->id ?? 'N/A',
                'name' => $this->warehouse->name ?? 'N/A',
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
            'receiving_date' => $this->receiving_date?->format('l, d F Y') ?? 'N/A',
            'receiving' => [
                'id' => $this->user->id ?? 'N/A',
                'name' => $this->user->name ?? 'N/A',
            ],
            'status' => $this->status,
            'created_by' => $this->created_by?->format('l, d F Y') ?? 'N/A',
            'updated_by' => $this->updated_by?->format('l, d F Y') ?? 'N/A',
        ];
    }
}
