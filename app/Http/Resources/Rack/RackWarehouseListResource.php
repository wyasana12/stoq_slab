<?php

namespace App\Http\Resources\Rack;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RackWarehouseListResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rack_code' => $this->rack_code,
            'warehouse' => [
                'id' => $this->warehouse?->id ?? 'N/A',
                'name' => $this->warehouse?->name ?? 'N/A',
            ],
            'status' => $this->status,
        ];
    }
}
