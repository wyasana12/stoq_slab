<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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

            'products'     => ['required', 'array'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.qty' => ['required', 'integer', 'min:1'],

            'priority'      => ['required', 'in:normal,high,urgent'],
            'reason'        => ['required', 'string', 'min:10'],
            'notes'        => ['nullable', 'string'],
        ];
    }
}
