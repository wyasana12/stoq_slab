<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $supplierId = $this->route('supplier')?->id;

        return [
            'supplier_code' => ['required', 'string', Rule::unique('suppliers', 'supplier_code')->ignore($supplierId)],
            'name' => ['required', 'string', Rule::unique('suppliers', 'name')->ignore($supplierId)],
            'contact_person' => ['required', 'string', Rule::unique('suppliers', 'contact_person')->ignore($supplierId)],
            'phone_number' => ['required', 'string', Rule::unique('suppliers', 'phone_number')->ignore($supplierId), 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'email', Rule::unique('suppliers', 'email')->ignore($supplierId)],
            'region_id' => ['required', 'exists:region,id'],
            'street' => ['required', 'string', 'min:5'],
            'postal_code' => ['required', 'string', 'min:5'],
            'status' => ['required', 'boolean']
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_code.required' => 'Supplier code is required.',
            'supplier_code.unique' => 'Supplier code has already been taken.',

            'name.required' => 'Supplier name is required.',
            'name.unique' => 'This supplier name has already been taken.',

            'contact_person.required' => 'Supplier contact person is required.',
            'contact_person.unique' => 'This supplier contact person has already been taken.',

            'phone_number.unique' => 'This supplier phone number has already been taken.',
            'phone_number.regex' => 'Warehouse phone number must be 7-15 digits and may optionally start with +.',

            'email.unique' => 'This supplier email has already been taken.',

            'street.required' => 'Supplier street is required.',
            'street.min' => 'Supplier location must be at least 5 characters.',

            'postal_code.required' => 'Supplier postal code is required.',
            'postal_code.min' => 'Supplier postal code must be at least 5 characters.',

            'status.required' => 'Supplier status is required.',
            'status.boolean' => 'Supplier status must be true or false.'
        ];
    }
}
