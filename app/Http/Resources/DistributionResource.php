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
            'warehouse_id'      => $this->warehouse_id,
            'location'          => $this->location,
            'dispatched_at'     => $this->dispatched_at,
            'requested_by'      => $this->requested_by,
            'confirmed_by'      => $this->confirmed_by,
            'notes'             => $this->notes,
            'status'            => $this->status,
            'items'             => StockDistributionItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
