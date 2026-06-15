<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreAndUpdateProductSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id', 'distinct'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.min_order_quantity' => ['required', 'integer', 'min:1'],
            'items.*.lead_time_days' => ['nullable', 'integer', 'min:0'],
            'items.*.return_limit_days' => ['nullable', 'integer', 'min:0'],
            'items.*.is_preferred' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier wajib dipilih.',
            'supplier_id.exists' => 'Supplier yang dipilih tidak ditemukan.',

            'items.required' => 'At least one product item is required.',
            'items.array' => 'The items must be an array format.',

            'items.*.product_id.required' => 'Product is required.',
            'items.*.product_id.exists' => 'One or more selected product are invalid or do not exists.',
            'items.*.product_id.distinct' => 'You cannot select the same product more than once.',

            'items.*.unit_price.required' => 'Unit price product is required.',
            'items.*.unit_price.numeric' => 'Unit price product must be a valid number.',
            'items.*.unit_price.min' => 'Unit price product cannot be less than 1.',

            'items.*.min_order_quantity.required' => 'Min order quantity product is required.',
            'items.*.min_order_quantity.integer' => 'Min order quantity product must be a number.',
            'items.*.min_order_quantity.min' => 'Min order quantity product cannot be less than 1.',

            'items.*.lead_time_days.integer' => 'Lead time days product must be a number.',
            'items.*.lead_time_days.min' => 'Lead time days product cannot be less than 0.',

            'items.*.return_limit_days.integer' => 'Return limit days must be a number.',
            'items.*.return_limit_days.min' => 'Return limit days cannot be less than 0.',

            'items.*.is_preferred.boolean' => 'Preferred must be true or false.',
        ];
    }
}
