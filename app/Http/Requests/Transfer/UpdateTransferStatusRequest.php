<?php

namespace App\Http\Requests\Transfer;

use App\Enums\TransferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateTransferStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(TransferStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.Illuminate\Validation\Rules\Enum' => 'Invalid status provided',
        ];
    }

    public function getStatus(): TransferStatus
    {
        return TransferStatus::from($this->validated('status'));
    }
}
