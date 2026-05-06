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

            'products'     => ['required', 'array'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.qty' => ['required', 'integer', 'min:1'],

            'status'       => ['nullable', new Enum(RestockStatus::class)],
            'notes'        => ['nullable', 'string'],
        ];
    }
}
