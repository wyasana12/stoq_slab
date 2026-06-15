<?php

namespace App\Http\Resources\Batch;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $origin = $this->resolveOrigin();

        return [
            'id' => $this->id,
            'batch_code' => $this->batch_code,
            'product' => [
                'id' => $this->product?->id ?? 'N/A',
                'name' => $this->product?->name ?? 'N/A',
                'initial_quantity' => $this->initial_quantity,
                'current_quantity' => $this->current_quantity,
                'price' => $this->price,
                'condition' => $this->condition,
                'production_date' => $this->production_date?->format('l, d F Y') ?? 'N/A',
                'expired_date' => $this->expired_date?->format('l, d F Y') ?? 'N/A',
            ],
            'racks' => $this->locations ? $this->locations->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'location_code' => $loc->location_code,
                ];
            }) : [],
            'supplier' => [
                'id' => $origin['id'],
                'name' => $origin['name']
            ],
        ];
    }

    private function resolveOrigin(): array
    {
        if (!$this->relationLoaded('receive') || !$this->receive || !$this->receive->receivable) {
            return [
                'id' => 'N/A',
                'name' => 'N/A'
            ];
        }

        $receivableType = $this->receive->receivable_type;
        $receivable = $this->receive->receivable;

        return match ($receivableType) {
            'purchase_order' => [
                'id' => $receivable->supplier?->id ?? 'N/A',
                'name' => $receivable->supplier?->name ?? 'N/A',
            ],
            'transfer' => [
                'id' => $receivable->fromWarehouse?->id ?? 'N/A',
                'name' => $receivable->fromWarehouse?->name
                    ? $receivable->fromWarehouse->name
                    : 'Warehouse (Transfer)',
            ],
            'restock' => [
                'id' => $receivable->supplier?->id ?? 'N/A',
                'name' => $receivable->supplier?->name ?? 'N/A',
            ],
            default => [
                'id' => 'N/A',
                'name' => 'N/A',
            ],
        };
    }
}
