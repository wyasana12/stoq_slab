<?php

namespace App\Http\Requests;

use App\Enums\ReturnStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmStockReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    ReturnStatus::APPROVED->value,
                    ReturnStatus::REJECTED->value,
                ]),
            ],
            'approved_quantity' => [
                'nullable',
                'integer',
                'min:1',
                'required_if:status,' . ReturnStatus::APPROVED->value,
            ],
            'confirmed_by' => ['required', 'integer', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
