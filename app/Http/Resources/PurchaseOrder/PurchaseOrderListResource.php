<?php

namespace App\Http\Resources\PurchaseOrder;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseOrderListResource extends JsonResource
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
                'id' => $this->supplier?->id ?? 'N/A',
                'name' => $this->supplier?->name ?? 'N/A',
            ],
            'warehouse' => [
                'id' => $this->warehouse->id ?? 'N/A',
                'name' => $this->warehouse->name ?? 'N/A',
            ],
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'order_date' => $this->order_date?->format('l, d F Y') ?? 'N/A',
            'approved_at' => $this->approved_at?->format('l, d F Y') ?? 'N/A',
            'created' => [
                'id' => $this->user->id ?? 'N/A',
                'name' => $this->user->name ?? 'N/A',
            ],
            'created_at' => $this->created_at?->format('l, d F Y'),
            'updated_at' => $this->updated_at?->format('l, d F Y'),
        ];
    }
}
