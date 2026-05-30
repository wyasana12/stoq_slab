<?php

namespace App\Http\Requests\Transfer;

use App\Enums\TransferStatus;
use App\Models\Batch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transfer_type' => ['required', 'in:in,out'],

            'product_id' => ['required', 'exists:products,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],

            'requested_by' => ['required', 'exists:users,id'],
            'confirmed_by' => ['sometimes', 'nullable', 'exists:users,id'],

            'status' => ['required', new Enum(TransferStatus::class)],
            'notes' => ['nullable', 'string'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
