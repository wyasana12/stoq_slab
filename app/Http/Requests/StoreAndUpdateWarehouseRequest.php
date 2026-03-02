<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAndUpdateWarehouseRequest extends FormRequest
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
        $warehouseId = $this->route('warehouse')?->id;

        return [
            'name' => ['required', 'string', Rule::unique('warehouses', 'name')->ignore($warehouseId)],
            'contact_person' => ['required', 'string', Rule::unique('warehouses', 'contact_person')->ignore($warehouseId)],
            'phone_number' => ['required', 'string', Rule::unique('warehouses', 'phone_number')->ignore($warehouseId), 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'email', Rule::unique('warehouses', 'email')->ignore($warehouseId)],
            'region_id' => ['required', 'exists:region,id'],
            'street' => ['required', 'string', 'min:5'],
            'postal_code' => ['required', 'string', 'min:5', 'max:5'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Warehouse name is required.',
            'name.unique' => 'This warehouse name has already been taken.',

            'contact_person.required' => 'Warehouse contact person is required.',
            'contact_person.unique' => 'Warehouse contact person has already been taken.',

            'phone_number.required' => 'Warehouse phone number is required.',
            'phone_number.unique' => 'Warehouse phone number has already been token.',
            'phone_number.regex' => 'Warehouse phone number must be 7-15 digits and may optionally start with +.',

            'email.unique' => 'Warehouse email has already been taken.',
            
            'regoin_id.required' => 'Region is required.',
            'region_id.exists' => 'This selected region is invalid.',
            
            'street.required' => 'Warehouse street is required.',
            'street.min' => 'Warehouse street must be at least 5 characters.',
            
            'postal_code.required' => 'Warehouse postal code is required.',
            'postal_code.min' => 'Warehouse postal code must be at least 5 characters.',
            'postal_code.max' => 'Warehouse postal code must be at most 5 characters.',
            
            'status.required' => 'Warehouse status is required.',
            'status.boolean' => 'Warehouse status must be true or false.',
        ];
    }
}
