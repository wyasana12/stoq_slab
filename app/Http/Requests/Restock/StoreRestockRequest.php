<?php

namespace App\Http\Requests\Restock;

use App\Enums\RestockStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreRestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sesuaikan izin
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'requested_by' => ['required', 'exists:users,id'],
            'supplier_id'  => ['nullable', 'exists:suppliers,id'],

            'products'     => ['required', 'array', 'min:1'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.quantity_requested' => ['required', 'integer', 'min:1'],
            'products.*.unit_price' => ['nullable', 'numeric', 'min:0'],

            'status'       => ['nullable', new Enum(RestockStatus::class)],
            'notes'        => ['nullable', 'string'],
            'reason'       => ['nullable', 'string'],
            'is_dss_recommendation' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required'                      => 'Gudang wajib diisi.',
            'warehouse_id.exists'                        => 'Gudang tidak ditemukan.',
            'requested_by.required'                      => 'Pengguna yang mengajukan wajib diisi.',
            'supplier_id.exists'                         => 'Supplier tidak ditemukan.',
            'products.required'                          => 'Minimal satu produk harus dipilih.',
            'products.array'                             => 'Data produk tidak valid.',
            'products.min'                               => 'Minimal satu produk harus ditambahkan.',
            'products.*.id.required'                     => 'Produk wajib dipilih pada setiap baris.',
            'products.*.id.exists'                       => 'Produk yang dipilih tidak ditemukan di sistem.',
            'products.*.quantity_requested.required'     => 'Jumlah produk wajib diisi.',
            'products.*.quantity_requested.integer'      => 'Jumlah produk harus berupa angka bulat.',
            'products.*.quantity_requested.min'          => 'Jumlah produk minimal harus 1 (tidak boleh 0).',
            'products.*.unit_price.numeric'              => 'Harga satuan harus berupa angka.',
            'products.*.unit_price.min'                  => 'Harga satuan tidak boleh negatif.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $products = $this->input('products', []);

        if (! is_array($products)) {
            return;
        }

        $mapped = array_map(function ($item) {
            if (! is_array($item)) {
                return $item;
            }

            if (isset($item['quantity_requested'])) {
                return $item;
            }

            if (isset($item['requested_quantity'])) {
                $item['quantity_requested'] = $item['requested_quantity'];
            } elseif (isset($item['qty'])) {
                $item['quantity_requested'] = $item['qty'];
            }

            return $item;
        }, $products);

        $this->merge(['products' => $mapped]);
    }
}
