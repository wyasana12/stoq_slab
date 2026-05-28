<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'distribution_code' => $this->distribution_code,
            'warehouse_id'      => $this->warehouse?->id,
            'warehouse_name'    => $this->warehouse?->name,
            'location'          => $this->location,
            'outlet_name'       => $this->outlet_name,
            'outlet_address'    => $this->outlet_address,
            'outlet_phone'      => $this->outlet_phone,
            'outlet_contact'    => $this->outlet_contact ?? $this->outlet_phone,
            'created_at'        => $this->created_at,
            'requested_by'      => $this->requested_by,
            'requested_by_name' => $this->request?->name,
            'confirmed_by'      => $this->confirmed_by,
            'confirmed_by_name' => $this->confirmedBy?->name,
            'dispatched_at'     => $this->dispatched_at,
            'notes'             => $this->notes,
            'status'            => $this->status,
            'items'             => StockDistributionItemResource::collection($this->whenLoaded('items')),
            'shipped_proof_url' => $this->when($this->shipped_proof_path, asset('storage/' . $this->shipped_proof_path)),
            'delivered_proof_url' => $this->when($this->delivered_proof_path, asset('storage/' . $this->delivered_proof_path)),
        ];
    }
}
