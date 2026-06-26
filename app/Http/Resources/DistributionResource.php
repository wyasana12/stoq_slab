<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shippedProofUrl = $this->when(
            $this->shipped_proof_path,
            asset('storage/' . $this->shipped_proof_path)
        );

        $completedProofUrl = $this->when(
            $this->completed_proof_path,
            asset('storage/' . $this->completed_proof_path)
        );

        $photoUrl = $completedProofUrl ?? $shippedProofUrl;

        return [
            'id'                => $this->id,
            'distribution_code' => $this->distribution_code,
            'store_id'          => $this->store?->id,
            'store_name'        => $this->store?->name,
            'store_address'     => $this->store?->street,
            'store_phone'       => $this->store?->phone_number,
            'warehouse_id'      => $this->warehouse?->id,
            'warehouse_name'    => $this->warehouse?->name,
            'created_at'        => $this->created_at,
            'requested_by'      => $this->requested_by,
            'requested_by_name' => $this->request?->name,
            'confirmed_by'      => $this->confirmed_by,
            'confirmed_by_name' => $this->confirmedBy?->name,
            'dispatched_at'     => $this->dispatched_at,
            'notes'             => $this->notes,
            'status'            => $this->status,
            'is_dss_recommendation' => $this->is_dss_recommendation,
            'items'             => StockDistributionItemResource::collection($this->whenLoaded('items')),

            'shipped_proof_url'   => $shippedProofUrl,
            'completed_proof_url' => $completedProofUrl,

            // Alias field yang dicari frontend
            'photo'    => $photoUrl,
            'photo_url' => $photoUrl,
            'image'    => $photoUrl,
            'photos'   => array_values(array_filter([
                $shippedProofUrl ? ['url' => $shippedProofUrl, 'type' => 'shipped'] : null,
                $completedProofUrl ? ['url' => $completedProofUrl, 'type' => 'completed'] : null,
            ])),
            'images'   => array_values(array_filter([
                $shippedProofUrl,
                $completedProofUrl,
            ])),
        ];
    }
}
