<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDistributionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'batch_id'           => $this->batch?->id,
            'batch_code'         => $this->batch?->batch_code,
            'product_id'         => $this->batch?->product?->id,
            'product_code'       => $this->batch?->product?->product_code,
            'product'            => $this->batch?->product?->name,
            'requested_quantity' => $this->requested_quantity,
            'approved_quantity'  => $this->approved_quantity,
        ];
    }
}
