<?php

namespace App\Http\Requests\Transfer;

use App\Enums\TransferStatus;
use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        $fromWarehouseId = $this->user()?->warehouse_id;
        $transferType = $this->input('transfer_type');

        if (empty($items) && $this->filled('products')) {
            $items = collect($this->input('products', []))
                ->map(function ($product) use ($fromWarehouseId) {
                    return [
                        'batch_id' => $this->resolveBatchId($product, $fromWarehouseId),
                        'requested_quantity' => data_get($product, 'requested_quantity', data_get($product, 'qty', 1)),
                        'approved_quantity' => data_get($product, 'approved_quantity', data_get($product, 'qty')),
                    ];
                })
                ->filter(fn($item) => ! is_null($item['batch_id']))
                ->all();
        }

        $this->merge([
            'from_warehouse_id' => $fromWarehouseId,
            'transfer_type' => $transferType,
            'notes' => $this->input('notes'),
            'items' => $items,
        ]);
    }

    protected function resolveBatchId(array $product, ?string $warehouseId): ?string
    {
        if (! empty($product['batch_id'])) {
            return $product['batch_id'];
        }

        $productId = data_get($product, 'product_id', data_get($product, 'id'));

        if (! $productId || ! $warehouseId) {
            return null;
        }

        return Batch::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('current_quantity', '>', 0)
            ->orderBy('expired_date')
            ->value('id');
    }

    public function rules(): array
    {
        return [
            'from_warehouse_id' => ['sometimes', 'exists:warehouses,id'],
            'to_warehouse_id' => ['sometimes', 'exists:warehouses,id'],
            'transfer_type' => ['sometimes', 'in:send,request'],
            'confirmed_by' => ['sometimes', 'nullable', 'exists:users,id'],
            'status' => ['sometimes', 'nullable', new Enum(TransferStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.batch_id' => ['required_with:items', 'exists:batches,id'],
            'items.*.requested_quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.approved_quantity' => ['nullable', 'integer', 'min:1'],
            'products.*.id' => ['required_without:products.*.batch_id', 'exists:products,id'],
            'products.*.batch_id' => ['sometimes', 'exists:batches,id'],
            'products.*.qty' => ['required_with:products', 'integer', 'min:1'],
        ];
    }
}
