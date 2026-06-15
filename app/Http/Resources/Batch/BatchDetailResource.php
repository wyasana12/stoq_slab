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
        $supplierId = $this->receive?->purchase?->supplier_id;
        $productId = $this->product_id;
        $returnLimitDays = null;

        if ($supplierId && $productId) {
            $productSupplierItem = \App\Models\ProductSupplierItem::where('product_id', $productId)
                ->where('supplier_id', $supplierId)
                ->first();
            $returnLimitDays = $productSupplierItem ? $productSupplierItem->return_limit_days : null;
        }

        $receivingDate = $this->receive?->receiving_date ?? $this->receive?->created_at;

        return [
            'id' => $this->id,
            'batch_code' => $this->batch_code,
            'receiving_id' => $this->receiving_id,
            'product' => [
                'id' => $this->product?->id ?? 'N/A',
                'name' => $this->product?->name ?? 'N/A',
                'initial_quantity' => $this->initial_quantity,
                'current_quantity' => $this->current_quantity,
                'price' => $this->price,
                'condition' => $this->condition,
                'rack_location' => $this->locations->first()?->location_code ?? 'N/A',
                'production_date' => $this->production_date?->format('l, d F Y') ?? 'N/A',
                'expired_date' => $this->expired_date?->format('l, d F Y') ?? 'N/A',
            ],
            'supplier' => [
                'id' => $this->receive?->purchase?->supplier->id ?? 'N/A',
                'name' => $this->receive?->purchase?->supplier->name ?? 'N/A'
            ],
            'receiving_date' => $receivingDate ? \Carbon\Carbon::parse($receivingDate)->toIso8601String() : null,
            'return_limit_days' => $returnLimitDays,
            'barcode' => $this->barcode ? asset('storage/' . $this->barcode) : 'N/A',
        ];
    }
}
