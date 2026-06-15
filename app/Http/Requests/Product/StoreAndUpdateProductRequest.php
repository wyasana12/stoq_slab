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
        ];
    }

    public function messages()
    {
        return [
            'sku.required' => 'Product SKU is required.',
            'sku.unique' => 'Product SKU has already been taken.',

            'name.required' => 'Product name is required.',
            'name.unique' => 'This product name has already been taken.',

            'category_id.required' => 'Category is required.',
            'category_id.exists' => 'This selected category is invalid.',

            'unit_id.required' => 'Unit is required.',
            'unit_id.exists' => 'This selected unit is invalid.',
        ];
    }
}
