<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_code' => $this->return_code,
            'warehouse_id' => $this->warehouse?->name,
            'batch_id' => $this->batch?->batch_code, // atau field lain yang kamu inginkan
            'requested_quantity' => $this->requested_quantity,
            'approved_quantity' => $this->approved_quantity,
            'reason' => $this->reason,
            'requested_by' => $this->request?->name,
            'confirmed_by' => $this->confirm?->name,
            'notes' => $this->notes,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
