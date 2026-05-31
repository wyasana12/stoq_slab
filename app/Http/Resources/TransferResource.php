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
            'notes' => $this->notes,
            'products' => $this->when(true, function () {
                // legacy: if transfer has item relation (many-items table), map them
                if ($this->relationLoaded('item') && $this->item) {
                    return $this->item->map(function ($item) {
                        return [
                            'id' => $item->batch->product?->id,
                            'name' => $item->batch->product?->name,
                            'qty' => $item->requested_quantity,
                        ];
                    });
                }

                // new schema: single product on transfer table
                $prod = $this->whenLoaded('products') ?? null;
                return [
                    [
                        'id' => $prod?->id ?? $this->product_id,
                        'name' => $prod?->name ?? null,
                        'qty' => $this->requested_quantity ?? 0,
                    ],
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
