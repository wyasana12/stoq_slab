<?php

namespace App\Http\Requests\Receive;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $table = match ($this->input('receivable_type')) {
            'purchase_order' => 'purchase_orders',
            'transfer'       => 'stock_transfers',
            'restock'        => 'restocks',
            default          => null,
        };

        return [
            'receivable_type' => ['required', 'string', Rule::in(['purchase_order', 'transfer', 'restock'])],
            'receivable_id'   => [
                'required',
                'string',
                $table ? Rule::exists($table, 'id') : '',
            ],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity_accepted' => ['required', 'integer', 'min:0'],
            'items.*.quantity_rejected' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['required', 'string', 'min:5'],

            'items.*.production_date' => ['nullable', 'date', 'before_or_equal:today'],
            'items.*.expired_date' => ['nullable', 'date', 'after_or_equal:today'],
            'items.*.condition' => ['nullable', 'string', 'min:4'],

            'items.*.racks' => ['required', 'array', 'min:1'],
            'items.*.racks.*.location_id' => ['required', 'distinct', 'exists:rack_locations,id'],
        ];
    }

    public function messages()
    {
        return [
            'receivable_type.required' => 'The document source type is required.',
            'receivable_type.in'       => 'The document source type must be either purchase_order, transfer, or restock.',
            'receivable_id.required'   => 'The document source ID is required.',
            'receivable_id.exists'     => 'The selected source document is invalid or does not exist.',

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

            'items.*.racks.required' => 'At least one rack is required.',
            'items.*.racks.array' => 'The items must be an array format.',

            'items.*.racks.*.location_id.required' => 'Rack is required.',
            'items.*.racks.*.location_id.exists' => 'One or more selected rack are invalid or do not exists.',
        ];
    }
}
