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
            'warehouse_id'      => $this->warehouse?->name,
            'location'          => $this->location,
            'created_at'        => $this->created_at,
            'requested_by'      => $this->request?->name,
            'confirmed_by'      => $this->confirmedBy?->name,
            'notes'             => $this->notes,
            'status'            => $this->status,
            'items'             => StockDistributionItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
