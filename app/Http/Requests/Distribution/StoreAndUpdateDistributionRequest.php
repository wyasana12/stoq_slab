<?php

namespace App\Http\Requests\Distribution;

use App\Models\Batch;
use App\Models\Store;
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

        // Resolve store information if store_id is provided
        $storeId = $this->input('store_id');
        if ($storeId) {
            $store = Store::find($storeId);
            if ($store) {
                $this->merge([
                    'outlet_name' => $store->name,
                    'outlet_address' => $store->address,
                    'outlet_phone' => $store->phone,
                ]);
            }
        }

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
            'store_id' => ($isCreate ? 'required' : 'sometimes') . '|exists:stores,id',
            'warehouse_id'          => ($isCreate ? 'required' : 'sometimes') . '|exists:warehouses,id',
            
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

    public function messages(): array
    {
        return [
            // Field utama
            'store_id.required'                      => 'Outlet harus dipilih.',
            'store_id.exists'                        => 'Outlet yang dipilih tidak valid.',
            'warehouse_id.required'                  => 'Gudang wajib diisi.',
            'warehouse_id.exists'                    => 'Gudang yang dipilih tidak valid.',
            'requested_by.required'                  => 'Data pemohon wajib diisi.',
            'requested_by.exists'                    => 'Pemohon tidak ditemukan.',
            'confirmed_by.exists'                    => 'Konfirmator tidak ditemukan.',
            'notes.max'                              => 'Catatan maksimal 255 karakter.',
            'dispatched_at.date'                     => 'Tanggal pengiriman tidak valid.',
            'status.in'                              => 'Status distribusi tidak valid.',

            // Items
            'items.required'                         => 'Item distribusi wajib diisi.',
            'items.array'                            => 'Format item distribusi tidak valid.',
            'items.min'                              => 'Minimal harus ada 1 item distribusi.',
            'items.*.batch_id.required_with'         => 'Batch produk wajib dipilih.',
            'items.*.batch_id.exists'                => 'Batch yang dipilih tidak valid.',
            'items.*.requested_quantity.required_with' => 'Jumlah item wajib diisi.',
            'items.*.requested_quantity.integer'     => 'Jumlah item harus berupa angka.',
            'items.*.requested_quantity.min'         => 'Jumlah item minimal 1.',
        ];
    }
}
