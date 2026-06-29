<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $shippedProofs = is_string($this->shipped_proofs) ? json_decode($this->shipped_proofs, true) : ($this->shipped_proofs ?? []);
        $completedProofs = is_string($this->completed_proofs) ? json_decode($this->completed_proofs, true) : ($this->completed_proofs ?? []);

        $shippedProofUrls = array_map(fn($path) => asset('storage/' . $path), $shippedProofs);
        $completedProofUrls = array_map(fn($path) => asset('storage/' . $path), $completedProofs);

        $photoUrl = $completedProofUrls[0] ?? $shippedProofUrls[0] ?? null;

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

            'shipped_proof_url'   => $shippedProofUrls[0] ?? null,
            'completed_proof_url' => $completedProofUrls[0] ?? null,
            'shipped_proof_urls'   => $shippedProofUrls,
            'completed_proof_urls' => $completedProofUrls,

            // Alias field yang dicari frontend
            'photo'    => $photoUrl,
            'photo_url' => $photoUrl,
            'image'    => $photoUrl,
            'photos'   => array_merge(
                array_map(fn($url) => ['url' => $url, 'type' => 'shipped'], $shippedProofUrls),
                array_map(fn($url) => ['url' => $url, 'type' => 'completed'], $completedProofUrls)
            ),
            'images'   => array_merge($shippedProofUrls, $completedProofUrls),
        ];
    }
}
