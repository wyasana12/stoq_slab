<?php

namespace App\Http\Requests\Distribution;

use App\Enums\DistributionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmDistributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_column(DistributionStatus::cases(), 'value')),
            ],
            'confirmed_by' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:255'],

            'items' => 'required_if:status,' . DistributionStatus::APPROVED->value . ',' . DistributionStatus::COMPLETED->value . '|array|min:1',
            'items.*.id' => 'required_with:items|exists:stock_distribution_items,id',
            'items.*.approved_quantity' => 'required_if:status,' . DistributionStatus::APPROVED->value . '|integer|min:0',
            'items.*.received_quantity' => 'required_if:status,' . DistributionStatus::COMPLETED->value . '|integer|min:0',
            'items.*.damaged_quantity' => 'required_if:status,' . DistributionStatus::COMPLETED->value . '|integer|min:0',

            'completed_proof' => 'required_if:status,' . DistributionStatus::COMPLETED->value
                . '|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $status = $this->input('status');
            
            if ($status === DistributionStatus::SHIPPED->value) {
                $distribution = $this->route('distribution');
                
                if ($distribution && !$distribution->flag_print) {
                    $validator->errors()->add('status', 'Surat jalan harus di-download (print) terlebih dahulu sebelum mengubah status menjadi Shipped.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.required'                       => 'Status harus diisi.',
            'status.in'                             => 'Status tidak valid.',
            'confirmed_by.required'                 => 'Konfirmator harus diisi.',
            'confirmed_by.exists'                   => 'Konfirmator tidak ditemukan.',
            'notes.max'                             => 'Catatan maksimal 255 karakter.',
            'items.required_if'                     => 'Item harus diisi saat approve.',
            'items.*.id.required_with'              => 'ID item tidak valid.',
            'items.*.approved_quantity.required_if' => 'Jumlah persetujuan harus diisi.',
            'items.*.approved_quantity.min'         => 'Jumlah persetujuan minimal 0.',
            'completed_proof.required_if'           => 'Foto bukti completed wajib diupload.',
            'completed_proof.file'                  => 'Bukti harus berupa file.',
            'completed_proof.mimes'                 => 'Bukti harus berformat jpg, jpeg, png, atau pdf.',
            'completed_proof.max'                   => 'Ukuran file maksimal 2MB.',
        ];
    }
}
