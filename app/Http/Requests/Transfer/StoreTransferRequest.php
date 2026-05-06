<?php

namespace App\Http\Requests\Transfer;

use App\Enums\TransferStatus;
use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        $fromWarehouseId = $this->user()?->warehouse_id;

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
            'requested_by' => $this->input('requested_by', $this->user()?->id),
            'status' => $this->input('status', TransferStatus::DRAFT->value),
            'notes' => $this->input('notes'),
            'reason' => $this->input('reason'),
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
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id'],

            'requested_by' => ['required', 'exists:users,id'],

            'status' => ['required', new Enum(TransferStatus::class)],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],

            'products'     => ['required', 'array'],
            'products.*.id' => ['required_without:products.*.batch_id', 'exists:products,id'],
            'products.*.batch_id' => ['sometimes', 'exists:batches,id'],
            'products.*.qty' => ['required', 'integer', 'min:1'],

            'items' => ['sometimes', 'array'],
            'items.*.batch_id' => ['required_with:items', 'exists:batches,id'],
            'items.*.requested_quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.approved_quantity' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
