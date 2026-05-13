<?php

namespace App\Http\Requests\Distribution;

use Illuminate\Foundation\Http\FormRequest;

class StoreAndUpdateDistributionRequest extends FormRequest
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
        $isCreate = $this->isMethod('post');
        return [
            'warehouse_id'          => ($isCreate ? 'required' : 'sometimes') . '|exists:warehouses,id',
            'location'              => ($isCreate ? 'required' : 'sometimes') . '|string|max:255',
            'requested_by'          => ($isCreate ? 'required' : 'sometimes') . '|exists:users,id',
            'confirmed_by'          => 'nullable|exists:users,id',
            'notes'                 => 'nullable|string|max:255',

            'items'                 => ($isCreate ? 'required' : 'sometimes') . '|array|min:1',
            'items.*.batch_id'      => 'required_with:items|exists:batches,id',
            'items.*.requested_quantity' => 'required_with:items|integer|min:1',
            'status' => 'nullable|string|in:draft,waiting-approval',
        ];
    }

    public function messages()
    {
        return [
            'warehouse_id.required' => 'Warehouse wajib diisi.',
            'location.required' => 'Location disribusi wajib diisi.',
            'requested_by.required' => 'Requested wajib diisi.',
            'items.required' => 'List item distribusi wajib diisi.',
            'items.*.batch_id.exists' => 'Batch yang dipilih tidak valid.',
            'items.*.requested_quantity.min' => 'Jumlah yang diminta harus minimal 1 untuk setiap item.',
            'status.in' => 'Status distribusi tidak valid.',
        ];
    }
}
