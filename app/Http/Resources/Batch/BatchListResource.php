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
        return [
            'id' => $this->id,
            'batch_code' => $this->batch_code,
            'receiving_id' => $this->receiving_id,
            'product' => [
                'id' => $this->product?->id ?? 'N/A',
                'name' => $this->product?->name ?? 'N/A',
                'current_quantity' => $this->current_quantity,
                'price' => $this->price,
                'production_date' => $this->production_date?->format('l, d F Y') ?? 'N/A',
                'expired_date' => $this->expired_date?->format('l, d F Y') ?? 'N/A',
            ],
            'supplier' => [
                'id' => $this->receive?->purchase?->supplier->id ?? 'N/A',
                'name' => $this->receive?->purchase?->supplier->name ?? 'N/A'
            ],
            'barcode' => [
                'value' => $this->batch_code,
                'preview_url' => route('batch.qr.preview', $this->id),
                'download_url' => route('batch.qr.download', $this->id),
            ],
            'created_at' => $this->created_at?->format('l, d F Y') ?? 'N/A',
            'updated_at' => $this->updated_at?->format('l, d F Y') ?? 'N/A'
        ];
    }
}
