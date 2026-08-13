<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAndUpdateUnitRequest extends FormRequest
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
        $unitId = $this->route('unit')?->id;
        return [
            'name' => ['required', 'string', Rule::unique('units', 'name')->ignore($unitId)],
            'symbol' => ['required', 'string', Rule::unique('units', 'symbol')->ignore($unitId)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Unit name is required.',
            'name.unique' => 'This unit name has already been taken.',

            'symbol.required' => 'Unit symbol is required.',
            'symbol.unique' => 'This unit symbol has already been taken.',
        ];
    }
}
