<?php

namespace App\Http\Requests\Disposal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmStockDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isRejected = $this->input('status') === 'rejected';

        return [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'approved_quantity' => ['required_if:status,approved', 'nullable', 'integer', $isRejected ? 'min:0' : 'min:1'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
