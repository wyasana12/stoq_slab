<?php

namespace App\Http\Requests\Restock;

use App\Enums\RestockStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateRestockStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Sesuaikan dengan policy authorization jika diperlukan
    }

    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(RestockStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => 'Status is required',
            'status.Illuminate\Validation\Rules\Enum' => 'Invalid status provided',
        ];
    }

    /**
     * Get the status as an Enum instance
     *
     * @return RestockStatus
     */
    public function getStatus(): RestockStatus
    {
        return RestockStatus::from($this->validated('status'));
    }
}
