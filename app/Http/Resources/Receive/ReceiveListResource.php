<?php

namespace App\Http\Resources\Receive;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiveListResource extends JsonResource
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
            'receiving_code' => $this->receiving_code,
            'source' => [
                'type' => $this->receivable_type,
                'id' => $this->receivable_id ?? 'N/A',
                'code' => $this->resolveSourceDocumentCode(),
                'date' => $this->resolveSourceDocumentDate(),
            ],
            'receiving_date' => $this->receiving_date?->format('l, d F Y') ?? 'N/A',
            'receiving' => [
                'id' => $this->user->id ?? 'N/A',
                'name' => $this->user->name ?? 'N/A',
            ],
            'status' => $this->status,
        ];
    }

    private function resolveSourceDocumentCode(): string
    {
        if (!$this->relationLoaded('receivable') || !$this->receivable) {
            return 'N/A';
        }

        return match ($this->receivable_type) {
            'purchase_order' => $this->receivable->po_code ?? 'N/A',
            'transfer'       => $this->receivable->transfer_code ?? 'N/A',
            'restock'        => $this->receivable->restock_code ?? 'N/A',
            default          => 'N/A',
        };
    }

    private function resolveSourceDocumentDate(): string
    {
        if (!$this->relationLoaded('receivable') || !$this->receivable) {
            return 'N/A';
        }

        $date = match ($this->receivable_type) {
            'purchase_order' => $this->receivable->order_date,
            'transfer'       => $this->receivable->updated_at,
            'restock'        => $this->receivable->updated_at,
            default          => null,
        };

        return $date ? $date->format('l, d F Y') : 'N/A';
    }
}
