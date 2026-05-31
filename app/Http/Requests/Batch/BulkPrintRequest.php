<?php

namespace App\Http\Requests\Batch;

use Illuminate\Foundation\Http\FormRequest;

class BulkPrintRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
        return [
            'batch_ids' => ['required', 'array'],
            'batch_ids.*' => ['required', 'string', 'exists:batches,id'],
        ];
    }

    public function messages(): array {
        return [
            'batch_ids.required' => 'At least one batch is required.',
            'batch_ids.array' => 'The batch must be an array format.',

            'batch_ids.*.required' => 'Batch is required.',
            'batch_ids.*.exists' => 'One or more selected batch are invalid or do not exists.',
        ];
    }
}
