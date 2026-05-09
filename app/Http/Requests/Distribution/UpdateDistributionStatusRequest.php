<?php

namespace App\Http\Requests\Distribution;

use App\Enums\DistributionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistributionStatusRequest extends FormRequest
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
                'string',
                Rule::in(array_column(DistributionStatus::cases(), 'value')),
            ],
            'confirmed_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:255',
        ];
    }
}
