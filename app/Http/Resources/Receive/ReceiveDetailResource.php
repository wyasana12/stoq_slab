<?php

namespace App\Http\Resources\Receive;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiveDetailResource extends JsonResource
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

            'warehouse' => $this->resolveWarehouse(),
            'supplier' => $this->resolveSupplier(),

            'source' => [
                'type' => $this->receivable_type,
                'id' => $this->receivable_id ?? 'N/A',
                'code' => $this->resolveSourceDocumentCode(),
                'date' => $this->resolveSourceDocumentDate(),
            ],

            'products' => $this->items->map(function ($i) {
                return [
                    'item_id' => $i->id,
                    'id' => $i->products->id ?? 'N/A',
                    'name' => $i->products->name ?? 'N/A',
                    'quantity_accepted' => $i->quantity_accepted,
                    'quantity_rejected' => $i->quantity_rejected,
                    'notes' => $i->notes,
                ];
            }),

            'receiving' => [
                'id' => $this->user->id ?? 'N/A',
                'name' => $this->user->name ?? 'N/A',
                'date' => $this->receiving_date,
            ],

            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Mengambil warehouse secara dinamis (Transfer In menggunakan toWarehouse).
     */
    private function resolveWarehouse(): array
    {
        if (!$this->relationLoaded('receivable') || !$this->receivable) {
            return [
                'id' => 'N/A',
                'name' => 'N/A',
            ];
        }

        $warehouse = match ($this->receivable_type) {
            'transfer' => $this->receivable->toWarehouse,
            default    => $this->receivable->warehouse,
        };

        return [
            'id' => $warehouse->id ?? 'N/A',
            'name' => $warehouse->name ?? 'N/A',
            'address' => $warehouse->street ? "{$warehouse->street}, {$warehouse->postal_code}" : null,
        ];
    }

    private function resolveSupplier(): array
    {
        if (!$this->relationLoaded('receivable') || !$this->receivable) {
            return [
                'id' => 'N/A',
                'name' => 'N/A',
            ];
        }

        $supplier = match ($this->receivable_type) {
            'transfer' => $this->receivable->fromWarehouse,
            default    => $this->receivable->supplier,
        };

        return [
            'id' => $supplier->id ?? 'N/A',
            'name' => $supplier->name ?? 'N/A',
            'address' => $supplier->street ? "{$supplier->street}, {$supplier->postal_code}" : null,
        ];
    }

    /**
     * Mengambil kode dokumen secara dinamis.
     */
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

    /**
     * Mengambil tanggal dokumen secara dinamis.
     */
    private function resolveSourceDocumentDate(): string
    {
        if (!$this->relationLoaded('receivable') || !$this->receivable) {
            return 'N/A';
        }

        $date = match ($this->receivable_type) {
            'purchase_order' => $this->receivable->order_date,
            'transfer'       => $this->receivable->transfer_date ?? $this->receivable->created_at,
            'restock'        => $this->receivable->restock_date ?? $this->receivable->created_at,
            default          => null,
        };

        return $date ? $date->format('l, d F Y') : 'N/A';
    }
}
