<?php

namespace App\Http\Resources\User;

use App\Models\Region;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserDetailResource extends JsonResource
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
            'name' => $this->name,
            'username' => $this->username,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            'region' => [
                'id' => $this->region_id,
                'full_address' => Region::getAddress($this->region_id),
                'levels' => Region::getRegionData($this->region_id),
            ],
            'address' => $this->street ? "{$this->street}, {$this->postal_code}" : null,
            'street' => $this->street,
            'postal_code' => $this->postal_code,
            'birth_date' => $this->birth_date?->format('l, d F Y') ?? 'N/A',
            'role' => [
                'id' => $this->roles->first()->id,
                'name' => $this->roles->first()->name,
            ],
            'warehouse' => [
                'id' => $this->warehouse?->id ?? 'N/A',
                'name' => $this->warehouse?->name ?? 'N/A',
            ],
        ];
    }
}
