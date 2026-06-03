<?php

namespace App\Http\Resources;

use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreDetailResource extends JsonResource
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
            'store_code' => $this->store_code,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'region' => [
                'id' => $this->region_id,
                'full_address' => Region::getAddress($this->region_id),
                'levels' => Region::getRegionData($this->region_id),
            ],
            'warehouse' => [
                'id' => $this->warehouse?->id ?? 'N/A',
                'name' => $this->warehouse?->name ?? 'N/A',
            ],
            'street' => $this->street,
            'postal_code' => $this->postal_code,
            'status' => $this->status,
        ];
    }
}
