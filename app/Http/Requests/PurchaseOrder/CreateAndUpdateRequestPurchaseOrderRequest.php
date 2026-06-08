<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\PurchaseOrderStatus;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateAndUpdateRequestPurchaseOrderRequest extends FormRequest
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
        $isSubmit = $this->input('status') === PurchaseOrderStatus::SUBMITTED->value;

        return [
            'supplier_id' => array_filter([$isSubmit ? 'required' : 'nullable', 'exists:suppliers,id']),

            'items' => array_filter([$isSubmit ? 'required' : 'nullable', 'array', $isSubmit ? 'min:1' : null]),
            'items.*.product_id' => array_filter([$isSubmit ? 'required' : 'nullable', 'exists:products,id']),
            'items.*.quantity_ordered' => array_filter([$isSubmit ? 'required' : 'nullable', 'integer', 'min:1']),
            'items.*.unit_price' => array_filter([$isSubmit ? 'required' : 'nullable', 'numeric', 'min:0']),

            'status' => ['required', Rule::in([PurchaseOrderStatus::DRAFT->value, PurchaseOrderStatus::SUBMITTED->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Supplier is required.',
            'supplier_id.exists' => 'This selected supplier is invalid.',

            'items.required' => 'At least one product item is required.',
            'items.array' => 'The items must be an array format.',

            'items.*.product_id.required' => 'Product is required.',
            'items.*.product_id.exists' => 'One or more selected product are invalid or do not exists.',

            'items.*.quantity_ordered.required' => 'Quantity order product is required.',
            'items.*.quantity_ordered.integer' => 'Quantity order product must be a number.',
            'items.*.quantity_ordered.min' => 'Quantity order product cannot be less than 1.',

            'items.*.unit_price.required' => 'Unit price product is required.',
            'items.*.unit_price.numeric' => 'Unit price product must be a valid number.',
            'items.*.unit_price.min' => 'Unit price product cannot be less than 1.',

            'status.in' => 'This selected status is invalid for request.',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);

            $productIds = collect($items)->pluck('product_id')->filter();

            if ($productIds->duplicates()->isNotEmpty()) {
                $validator->errors()->add(
                    'items',
                    'Duplicate products are not allowed in a single purchase order.'
                );
            }
        });
    }
}
