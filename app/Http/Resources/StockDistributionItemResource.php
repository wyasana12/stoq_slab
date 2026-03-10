<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockDistributionItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'batch_id'           => $this->batch_id,
            'requested_quantity' => $this->requested_quantity,
            'approved_quantity'  => $this->approved_quantity,
        ];
    }
}
