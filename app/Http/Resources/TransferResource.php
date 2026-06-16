<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_code' => $this->transfer_code,
            'transfer_type' => $this->transfer_type,
            'from_warehouse' => $this->when($this->relationLoaded('fromWarehouse'), $this->fromWarehouse?->name),
            'to_warehouse' => $this->when($this->relationLoaded('toWarehouse'), $this->toWarehouse?->name),
            'requested_by' => $this->request->name ?? null,
            'confirmed_by' => $this->confirm?->name,
            'status' => $this->status instanceof \App\Enums\TransferStatus ? $this->status->value : $this->status,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'requested_quantity' => $this->requested_quantity,
            'approved_quantity' => $this->approved_quantity,
            'products' => $this->when(true, function () {
                $statusValue = $this->status instanceof \App\Enums\TransferStatus ? $this->status->value : $this->status;

                // legacy: if transfer has item relation (many-items table), map them
                if ($this->relationLoaded('item') && $this->item) {
                    return $this->item->map(function ($item) use ($statusValue) {
                        return [
                            'id' => $item->batch->product?->id,
                            'name' => $item->batch->product?->name,
                            'qty' => ($statusValue !== 'draft' && $item->approved_quantity > 0) ? $item->approved_quantity : $item->requested_quantity,
                        ];
                    });
                }

                // new schema: single product on transfer table
                $prod = $this->whenLoaded('products') ?? null;
                return [
                    [
                        'id' => $prod?->id ?? $this->product_id,
                        'name' => $prod?->name ?? null,
                        'qty' => ($statusValue !== 'draft' && $this->approved_quantity > 0) ? $this->approved_quantity : ($this->requested_quantity ?? 0),
                    ],
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
