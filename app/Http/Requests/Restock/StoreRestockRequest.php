<?php

namespace App\Http\Requests\Restock;

use App\Enums\RestockStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sesuaikan izin
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'requested_by' => ['required', 'exists:users,id'],
            'supplier_id'  => ['nullable', 'exists:suppliers,id'],

            'products'     => ['required', 'array'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.quantity_requested' => ['required', 'integer', 'min:1'],
            'products.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'status'       => ['nullable', new Enum(RestockStatus::class)],
            'notes'        => ['nullable', 'string'],
            'is_dss_recommendation' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $products = $this->input('products', []);

        if (! is_array($products)) {
            return;
        }

        $mapped = array_map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }

            if (isset($item['quantity_requested'])) {
                return $item;
            }

            if (isset($item['requested_quantity'])) {
                $item['quantity_requested'] = $item['requested_quantity'];
            } elseif (isset($item['qty'])) {
                $item['quantity_requested'] = $item['qty'];
            }

            return $item;
        }, $products);

        $this->merge(['products' => $mapped]);
    }
}
