<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAndUpdateProductRequest extends FormRequest
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
        $productId = $this->route('product')?->id;

        return [
            'sku' => ['required', 'string', Rule::unique('products', 'sku')->ignore($productId)],
            'name' => ['required', 'string', Rule::unique('products', 'name')->ignore($productId)],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_id' => ['required', 'exists:suppliers,id', 'distinct'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.min_order_quantity' => ['required', 'integer', 'min:1'],
            'items.*.lead_time_days' => ['nullable', 'integer', 'min:1'],
            'items.*.return_limit_days' => ['nullable', 'integer', 'min:1'],
            'items.*.is_preferred' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required.',
            'name.unique' => 'This product name has already been taken.',

            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'This selected category is invalid.',

            'unit_id.required' => 'Unit is required.',
            'unit_id.exists' => 'This selected unit is invalid.',

            'items.required' => 'At least one product item is required.',
            'items.array' => 'The items must be an array format.',

            'items.*.supplier_id.required' => 'Supplier is required.',
            'items.*.supplier_id.exists' => 'One or more selected supplier are invalid or do not exists.',
            'items.*.supplier_id.distinct' => 'You cannot select the same supplier more than once.',

            'items.*.unit_price.required' => 'Unit price product is required.',
            'items.*.unit_price.numeric' => 'Unit price product must be a valid number.',
            'items.*.unit_price.min' => 'Unit price product cannot be less than 1.',

            'items.*.min_order_quantity.required' => 'Min order quantity product is required.',
            'items.*.min_order_quantity.integer' => 'Min order quantity product must be a number.',
            'items.*.min_order_quantity.min' => 'Min order quantity product cannot be less than 1.',
            
            'items.*.lead_time_days.integer' => 'Lead time days product must be a number.',
            'items.*.lead_time_days.min' => 'Lead time days product cannot be less than 1.',

            'items.*.return_limit_days.integer' => 'Return limit days must be a number.',
            'items.*.return_limit_days.min' => 'Return limit days cannot be less than 0.',

            'items.*.is_preferred.boolean' => 'Preferred must be true or false.', 
        ];
    }
}
