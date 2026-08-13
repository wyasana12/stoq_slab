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
        $capacityCurrent = $this->locations->sum('used');
        $capacityTotal   = $this->locations->sum('capacity');

        // Calculate levels and bins per level from actual rack_locations data
        $levels      = $this->locations->max('level') ?? 0;
        $binsPerLevel = $levels > 0
            ? (int) ceil($this->locations->count() / $levels)
            : 0;

        return [
            'id'              => $this->id,
            'code'            => $this->rack_code,
            'statusLabel'     => ucfirst(strtolower($this->status ?? 'available')),
            'statusVariant'   => $this->mapStatusVariant($this->status),
            'capacityCurrent' => $capacityCurrent,
            'capacityTotal'   => $capacityTotal,
            'capacityUnit'    => 'unit',
            'productCount'    => $this->locations->where('used', '>', 0)->count(),
            'levels'          => $levels,
            'binsPerLevel'    => $binsPerLevel,
            'availableCount'  => $capacityTotal - $capacityCurrent,
            'locations'       => $this->locations->sortBy(['level', 'bin'])->map(fn ($loc) => [
                'id'            => $loc->id,
                'level'         => $loc->level,
                'bin'           => $loc->bin,
                'location_code' => $loc->location_code,
                'capacity_unit' => $loc->capacity_unit,
                'capacity'      => $loc->capacity,
                'used'          => $loc->used,
                'status'        => $loc->status,
                'batch'         => $loc->relationLoaded('batch') && $loc->batch ? [
                    'id' => $loc->batch->id,
                    'batch_code' => $loc->batch->batch_code,
                    'product' => $loc->batch->relationLoaded('product') && $loc->batch->product ? [
                        'id' => $loc->batch->product->id,
                        'name' => $loc->batch->product->name,
                    ] : null,
                ] : null,
            ])->values()->all(),
        ];
    }

    /**
     * Map backend status to frontend status variant.
     */
    private function mapStatusVariant(?string $status): string
    {
        return match (strtoupper($status ?? '')) {
            'AVAILABLE'   => 'success',
            'FULL'        => 'danger',
            'MAINTENANCE' => 'maintenance',
            default       => 'info',
        };
    }
}
