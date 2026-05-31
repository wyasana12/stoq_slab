<?php

namespace App\Http\Requests\Distribution;

use App\Models\Batch;
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

    protected function prepareForValidation(): void
    {
        $distribution = $this->route('distribution');
        $items = $this->input('items');
        $outletPhone = $this->input('outlet_phone', $this->input('outlet_contact'));

        if (is_array($items)) {
            $items = array_map(function ($item) {
                if (! is_array($item)) {
                    return $item;
                }

                $batchId = $item['batch_id'] ?? null;

                if (! $batchId && isset($item['batch_code'])) {
                    $batchId = $item['batch_code'];
                }

                if ($batchId) {
                    $batch = Batch::query()
                        ->where('id', $batchId)
                        ->orWhere('batch_code', $batchId)
                        ->first();

                    if ($batch) {
                        $item['batch_id'] = $batch->id;
                    }
                }

                return $item;
            }, $items);
        }

        $this->merge([
            'outlet_phone' => $outletPhone,
            'requested_by' => $this->input(
                'requested_by',
                $distribution?->requested_by ?? $this->user()?->id
            ),
            'items' => $items,
        ]);
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
            'outlet_name'           => ($isCreate ? 'required' : 'sometimes') . '|string|max:255',
            'outlet_address'        => ($isCreate ? 'required' : 'sometimes') . '|string|max:255',
            'outlet_phone'          => ($isCreate ? 'required' : 'sometimes') . '|numeric',
            'outlet_contact'        => 'sometimes|nullable|numeric',
            'requested_by'          => ($isCreate ? 'required' : 'sometimes') . '|exists:users,id',
            'confirmed_by'          => 'nullable|exists:users,id',
            'notes'                 => 'nullable|string|max:255',
            'dispatched_at'         => 'nullable|date',

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
            'outlet_name.required' => 'Nama outlet wajib diisi.',
            'outlet_address.required' => 'Alamat outlet wajib diisi.',
            'outlet_phone.required' => 'Telepon outlet wajib diisi.',
            'requested_by.required' => 'Requested wajib diisi.',
            'items.required' => 'List item distribusi wajib diisi.',
            'items.*.batch_id.exists' => 'Batch yang dipilih tidak valid.',
            'items.*.requested_quantity.min' => 'Jumlah yang diminta harus minimal 1 untuk setiap item.',
            'status.in' => 'Status distribusi tidak valid.',
        ];
    }
}
