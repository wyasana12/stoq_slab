<?php

namespace App\Http\Requests\Receive;

use App\Enums\ReceiveStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateProductReceiveRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'exists:product_receiving_items,id'],
            'items.*.quantity_accepted' => ['required', 'integer', 'min:0'],
            'items.*.quantity_rejected' => ['required', 'integer', 'min:0'],
            'items.*.notes' => ['required', 'string', 'min:5'],

            'status' => ['required', new Enum(ReceiveStatus::class)],
        ];
    }

    public function messages()
    {
        return [
            'items.required' => 'At least one product item is required.',
            'items.array' => 'The items must be an array format.',

            'items.*.id.required' => 'Product is required.',
            'items.*.id.exists' => 'One or more selected product are invalid or do not exists.',

            'items.*.quantity_accepted.required' => 'Quantity accepted product is required.',
            'items.*.quantity_accepted.integer' => 'Quantity accepted product must be a number.',
            'items.*.quantity_accepted.min' => 'Quantity accepted product cannot be less than 0.',

            'items.*.quantity_rejected.required' => 'Quantity rejected product is required.',
            'items.*.quantity_rejected.integer' => 'Quantity rejected product must be a number.',
            'items.*.quantity_rejected.min' => 'Quantity rejected product cannot be less than 0.',

            'items.*.notes.required' => 'Notes product is required.',
            'items.*.notes.string' => 'Notes product must be a string format.',
            'items.*.notes.min' => 'Notes product cannot be less than 5.',

            'status.required' => 'Product receive status is required.',
        ];
    }
}
