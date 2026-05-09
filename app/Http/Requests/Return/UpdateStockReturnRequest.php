<?php

namespace App\Http\Requests\Return;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStockReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(['damaged', 'expired', 'mismatch_po'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
