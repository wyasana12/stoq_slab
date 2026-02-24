<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Symfony\Component\Uid\Ulid;

class UnitResource extends JsonResource
{
    /**
     * @property Ulid $id example aklsdnwkajh21312
     * @property string $name example Kilogram
     * @property string $symbol example Kg
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'symbol' => $this->symbol,
        ];
    }
}
