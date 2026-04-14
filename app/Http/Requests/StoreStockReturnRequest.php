<?php

namespace App\Http\Requests;

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
            'batch_id' => ['required', 'string', 'exists:batches,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(['damaged', 'expired', 'mismatch_po'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'requested_by' => ['required', 'string', 'exists:users,id'],
        ];
    }
}
