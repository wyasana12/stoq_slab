<?php

namespace App\Http\Requests\Disposal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockDisposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'batch_id' => ['required', 'string', 'exists:batches,id'],
            'product_id' => ['required', 'string', 'exists:products,id'],
            'requested_quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['required', Rule::in(['damaged', 'expired', 'production_defect'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'damage_proof' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:5120'],
        ];
    }
}
