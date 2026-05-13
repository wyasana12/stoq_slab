<?php

namespace App\Http\Resources\User;

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
