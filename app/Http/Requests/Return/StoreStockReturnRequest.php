<?php

namespace App\Http\Requests\Return;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receiving_id' => ['required', 'string', 'exists:product_receivings,id'],
            'product_id' => ['required', 'string', 'exists:products,id'],
            'warehouse_id' => ['required', 'string', 'exists:warehouses,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(['damaged', 'expired', 'mismatch_po'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
