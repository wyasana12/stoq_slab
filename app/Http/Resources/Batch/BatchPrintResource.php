<?php

namespace App\Http\Resources\Batch;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BatchPrintResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $barcodeBase64 = null;

        if (!empty($this->barcode) && Storage::disk('public')->exists($this->barcode)) {
            $svgContent = Storage::disk('public')->get($this->barcode);  
            $barcodeBase64 = 'data:image/svg+xml;base64,' . base64_encode($svgContent);
        }

        return [
            'id' => $this->id,
            'batch_code' => $this->batch_code,
            'product' => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
                'price' => $this->price,
                'production_date' => $this->production_date?->format('Y-m-d'),
                'expired_date' => $this->expired_date?->format('Y-m-d'),
                'unit' => $this->product?->unit?->name,
                'symbol' => $this->product?->unit?->symbol
            ],
            'barcode' => [
                'value' => $this->batch_code,
                'preview_url' => route('batch.qr.preview', $this->id),
                'image_base64' => $barcodeBase64,
            ],
        ];
    }
}
