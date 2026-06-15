<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RestockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'restock_code' => $this->restock_code,
            'warehouse'    => $this->warehouse->name ?? null,
            'supplier_id'  => $this->supplier_id,
            'supplier'     => $this->supplier->name ?? null,
            'requested_by' => $this->request->name ?? null,
            'confirmed_by' => $this->confirm?->name,
            'total_amount' => $this->total_amount,
            'status'       => $this->status instanceof \App\Enums\RestockStatus ? $this->status->value : $this->status,
            'notes'        => $this->notes,
            'products' => $this->item->map(function ($item) {
                return [
                    'id'   => $item->product->id,
                    'name' => $item->product->name,
                    'qty'  => $item->requested_quantity,
                    'unit_price' => $item->unit_price,
                ];
            }),
            'created_at'   => $this->created_at,
        ];
    }
}
