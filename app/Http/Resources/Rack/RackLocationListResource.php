<?php

namespace App\Http\Resources\Rack;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RackLocationListResource extends JsonResource
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
            'level' => $this->level,
            'bin' => $this->bin,
            'location_code' => $this->location_code,
            'capacity_unit' => $this->capacity_unit,
            'capacity' => $this->capacity,
            'used' => $this->used,
            'status' => $this->status,
        ];
    }
}
