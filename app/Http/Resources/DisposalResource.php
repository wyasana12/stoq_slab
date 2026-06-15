<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DisposalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $damageProofUrl = $this->when(
            $this->damage_proof_path,
            asset('storage/' . $this->damage_proof_path)
        );

        return [
            'id' => $this->id,
            'disposal_code' => $this->disposal_code,
            'batch_id' => $this->batch_id,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
            'product_code' => $this->product?->product_code,
            'warehouse_name' => $this->warehouse?->name,
            'requested_quantity' => $this->requested_quantity,
            'approved_quantity' => $this->approved_quantity,
            'reason' => $this->reason,
            'requested_by_name' => $this->request?->name,
            'confirmed_by_name' => $this->confirm?->name,
            'notes' => $this->notes,
            'status' => $this->status,
            'damage_proof_url' => $damageProofUrl,
            'photo' => $damageProofUrl,
            'photo_url' => $damageProofUrl,
            'image' => $damageProofUrl,
            'photos' => $damageProofUrl ? [['url' => $damageProofUrl, 'type' => 'damage_proof']] : [],
            'images' => $damageProofUrl ? [$damageProofUrl] : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
