<?php

namespace App\Http\Resources\Batch;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BatchListResource extends JsonResource
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
                'current_quantity' => $this->current_quantity,
                'price' => $this->price,
                'production_date' => $this->production_date?->format('l, d F Y') ?? 'N/A',
                'expired_date' => $this->expired_date?->format('l, d F Y') ?? 'N/A',
            ],
            'supplier' => [
                'id' => $origin['id'],
                'name' => $origin['name']
            ],
            // 'barcode' => [
            //     'value' => $this->batch_code,
            //     'preview_url' => route('batch.qr.preview', $this->id),
            //     'download_url' => route('batch.qr.download', $this->id),
            // ],
            // 'created_at' => $this->created_at?->format('l, d F Y') ?? 'N/A',
            // 'updated_at' => $this->updated_at?->format('l, d F Y') ?? 'N/A'
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
