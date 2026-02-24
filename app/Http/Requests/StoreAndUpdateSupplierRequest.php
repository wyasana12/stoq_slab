<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAndUpdateSupplierRequest extends FormRequest
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
            'name' => ['required', 'string', 'unique:suppliers,name'],
            'location' => ['nullable', 'string', 'min:5'],
            'phone_number' => ['nullable', 'string', 'unique:suppliers,phone_number', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }

    public function messages(): array {
        return [
            'name.required' => 'Supplier name is required.',
            'name.unique' => 'This supplier name has already been taken.',

            'location.min' => 'Supplier location must be less than 0.',
            
            'phone_number.unique' => 'This supplier phone number has already been taken.',
            'phone_number.regex' => 'Warehouse phone number must be 7-15 digits and may optionally start with +.',
        ];
    }
}
