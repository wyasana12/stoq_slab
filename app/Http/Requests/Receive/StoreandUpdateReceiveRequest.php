<?php

namespace App\Http\Requests\Receive;

use Illuminate\Foundation\Http\FormRequest;

class StoreandUpdateReceiveRequest extends FormRequest
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
            'purchase_id' => ['required', 'string','exists:purchase_orders,id'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity_accepted' => ['required', 'integer', 'min:0'],
            'items.*.quantity_rejected' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['required', 'string', 'min:5'],

            'items.*.production_date' => ['nullable', 'date', 'before_or_equal:today'],
            'items.*.expired_date' => ['nullable', 'date', 'after_or_equal:today'],
            'items.*.condition' => ['nullable', 'string', 'min:4'],
        ];
    }

    public function messages()
    {
        return [
            'purchase_id.required' => 'Purchase Order is required.',
            'purchase_id.exists' => 'This selected purchase is invalid.',

            'status.required' => 'Status receive is required.',
            
            'items.required' => 'At least one product is required.',
            'items.array' => 'The items must be an array format.',
            'items.*.product_id.required' => 'Product is required.',
            'items.*.product_id.exists' => 'One or more selected product are invalid or do not exists.',
            'items.*.quantity_accepted.required' => 'Quantity accepted product is required.',
            'items.*.quantity_accepted.integer' => 'Quantity accepted product must be a number.',
            'items.*.quantity_accepted.min' => 'Quantity accepted product cannot be less than 0.',
            'items.*.quantity_rejected.required' => 'Quantity rejected product is required.',
            'items.*.quantity_rejected.integer' => 'Quantity rejected product must be a number.',
            'items.*.quantity_rejected.min' => 'Quantity rejected cannot be less than 0.',

            'items.*.production_date.date' => 'Production date must be a valid date.',
            'items.*.production_date.before_or_equal' => 'Production date cannot be a future date.',
            
            'items.*.expired_date.date' => 'Expired date must be a valid date.',
            'items.*.expired_date.after_or_equal' => 'Expired date cannot be a past date.',

            'items.*.condition.min' => 'Condition product cannot be less than 4 characters.',
        ];
    }
}
