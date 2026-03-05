<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'sku' => ['required', 'string', 'unique:products,sku'],
            'name' => ['required', 'string', 'unique:products,name'],
            'category_id' => ['required', 'exists:categories,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'return_limit_days' => ['nullable', 'integer', 'min:0'],
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

            'min_quantity.integer' => 'Minimum quantity must be a number.',
            'min_quantity.min' => 'Minimum quantity cannot be less than 0.',

            'return_limit_days.integer' => 'Return limit days must be a number.',
            'return_limit_days.min' => 'Return limit days cannot be less than 0.',
        ];
    }
}
