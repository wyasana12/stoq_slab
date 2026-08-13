<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $damageProofUrl = $this->when(
            $this->damage_proof_path,
            asset('storage/' . $this->damage_proof_path)
        );

        return [
            'id' => $this->id,
            'return_code' => $this->return_code,
            'receiving_id' => $this->receiving_id,
            'receiving_code' => $this->receiving?->receiving_code,
            'receiving_date' => $this->receiving?->receiving_date,
            'product_id' => $this->product_id,
            'product_name' => $this->product?->name,
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
