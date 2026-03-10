<?php

namespace App\Http\Requests;

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
        return [
            'warehouse_id'          => 'required|exists:warehouses,id',
            'location'              => 'required|string|max:255',
            'requested_by'          => 'nullable|exists:users,id',
            'confirmed_by'          => 'nullable|exists:users,id',

            'items'                 => 'required|array|min:1',
            'items.*.batch_id'      => 'required|exists:batches,id',
            'items.*.requested_quantity' => 'required|integer|min:1',
        ];
    }

    public function messages()
    {
        return [
            'warehouse_id.required' => 'Warehouse wajib diisi.',
            'location.required' => 'Location disribusi wajib diisi.',
            'items.required' => 'List item distribusi wajib diisi.',
            'items.*.batch_id.exists' => 'Batch yang dipilih tidak valid.',
            'items.*.requested_quantity.min' => 'Jumlah yang diminta harus minimal 1 untuk setiap item.',
        ];
    }
}
