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
            'status'            => ['required', new Enum(TransferStatus::class)],
            'approved_quantity' => ['required', 'integer', $isRejected ? 'min:0' : 'min:1'],
            'from_warehouse_id' => ['sometimes', $isRejected ? 'nullable' : 'required', 'exists:warehouses,id'],
            'to_warehouse_id'   => ['sometimes', $isRejected ? 'nullable' : 'required', 'exists:warehouses,id'],
            'confirmation_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required'               => 'Status konfirmasi wajib dipilih.',
            'status.Illuminate\Validation\Rules\Enum' => 'Status yang dipilih tidak valid.',
            'approved_quantity.required'    => 'Jumlah yang disetujui wajib diisi.',
            'approved_quantity.integer'     => 'Jumlah yang disetujui harus berupa angka bulat.',
            'approved_quantity.min'         => 'Jumlah yang disetujui minimal harus 1 (tidak boleh 0).',
            'from_warehouse_id.required'    => 'Gudang asal wajib dipilih.',
            'from_warehouse_id.exists'      => 'Gudang asal tidak ditemukan di sistem.',
            'to_warehouse_id.required'      => 'Gudang tujuan wajib dipilih.',
            'to_warehouse_id.exists'        => 'Gudang tujuan tidak ditemukan di sistem.',
            'confirmation_note.max'         => 'Alasan konfirmasi maksimal 255 karakter.',
        ];
    }
}
