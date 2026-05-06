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
            'from_warehouse' => $this->when($this->relationLoaded('fromWarehouse'), $this->fromWarehouse?->name),
            'to_warehouse' => $this->when($this->relationLoaded('toWarehouse'), $this->toWarehouse?->name),
            'requested_by' => $this->request->name ?? null,
            'confirmed_by' => $this->confirm?->name,
            'status' => $this->status instanceof \App\Enums\TransferStatus ? $this->status->value : $this->status,
            'notes' => $this->notes,
            'products' => $this->item->map(function ($item) {
                return [
                    'id' => $item->batch->product?->id,
                    'name' => $item->batch->product?->name,
                    'qty' => $item->requested_quantity,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
