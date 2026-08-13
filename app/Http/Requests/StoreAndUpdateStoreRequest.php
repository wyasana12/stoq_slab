<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAndUpdateStoreRequest extends FormRequest
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
        $storeId = $this->route('store')?->id;
        return [
            'store_code' => ['required', 'string', Rule::unique('stores', 'store_code')->ignore($storeId)],
            'name' => ['required', 'string', Rule::unique('stores', 'name')->ignore($storeId)],
            'contact_person' => ['required', 'string', Rule::unique('stores', 'contact_person')->ignore($storeId)],
            'phone_number' => ['required', 'string', Rule::unique('stores', 'phone_number')->ignore($storeId), 'regex:/^\+?[0-9]{7,15}$/'],
            'email' => ['nullable', 'email', Rule::unique('stores', 'email')->ignore($storeId)],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'region_id' => ['required', 'exists:region,id'],
            'street' => ['required', 'string', 'min:5'],
            'postal_code' => ['required', 'digits:5'],
            'status' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_code.required' => 'Store code is required.',
            'store_code.unique' => 'Store code has already been taken.',

            'name.required' => 'Store name is required.',
            'name.unique' => 'This store name has already been taken.',

            'contact_person.required' => 'Store contact person is required.',
            'contact_person.unique' => 'Store contact person has already been taken.',

            'phone_number.required' => 'Store phone number is required.',
            'phone_number.unique' => 'Store phone number has already been token.',
            'phone_number.regex' => 'Store phone number must be 7-15 digits and may optionally start with +.',

            'email.unique' => 'Store email has already been taken.',

            'warehouse_id.required' => 'Warehouse is required.',
            'warehouse_id.exists' => 'This selected warehouse is invalid.',

            'region_id.required' => 'Region is required.',
            'region_id.exists' => 'This selected region is invalid.',

            'street.required' => 'Store street is required.',
            'street.min' => 'Store street must be at least 5 characters.',

            'postal_code.required' => 'Store postal code is required.',
            'postal_code.digits' => 'Store postal code must be exactly 5 digits.',

            'status.required' => 'Store status is required.',
            'status.boolean' => 'Store status must be true or false.',
        ];
    }
}
