<?php

namespace App\Http\Requests\Rack;

use Illuminate\Foundation\Http\FormRequest;

class StoreRackRequest extends FormRequest
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
            'category_id'    => ['required', 'string', 'exists:categories,id'],
            'levels'         => ['required', 'integer', 'min:1', 'max:10'],
            'bins_per_level' => ['required', 'integer', 'min:1', 'max:20'],
            'capacity'       => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages()
    {
        return [
            'category_id.required'    => 'Kategori wajib dipilih.',
            'category_id.exists'      => 'Kategori tidak valid.',
            'levels.required'         => 'Jumlah Level wajib diisi.',
            'levels.integer'          => 'Jumlah Level harus berupa angka.',
            'levels.min'              => 'Jumlah Level minimal 1.',
            'levels.max'              => 'Jumlah Level maksimal 10.',
            'bins_per_level.required' => 'Bin Per Level wajib diisi.',
            'bins_per_level.integer'  => 'Bin Per Level harus berupa angka.',
            'bins_per_level.min'      => 'Bin Per Level minimal 1.',
            'bins_per_level.max'      => 'Bin Per Level maksimal 20.',
            'capacity.required'       => 'Kapasitas per Bin wajib diisi.',
            'capacity.integer'        => 'Kapasitas harus berupa angka.',
            'capacity.min'            => 'Kapasitas minimal 1.',
        ];
    }
}
