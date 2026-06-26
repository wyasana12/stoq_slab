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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isRejected = $this->input('status') === 'rejected';

        return [
            'status' => ['required', new Enum(TransferStatus::class)],
            'approved_quantity' => ['required', 'integer', $isRejected ? 'min:0' : 'min:1'],
            'from_warehouse_id' => ['sometimes', $isRejected ? 'nullable' : 'required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['sometimes', $isRejected ? 'nullable' : 'required', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
