<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => ['required', 'string', 'unique:warehouses,name'],
            'location' => ['required', 'string', 'min:5'],
            'phone_number' => ['required', 'string', 'unique:warehouses,phone_number', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Warehouse name is required.',
            'name.unique' => 'This warehouse name has already been token.',

            'location.required' => 'Warehouse location is required.',
            'location.min' => 'Warehouse location cannot be less than 0.',

            'phone_number.required' => 'Warehouse phone number is required.',
            'phone_number.unique' => 'Warehouse phone number has already been token.',
            'phone_number.regex' => 'Warehouse phone number must be 7-15 digits and may optionally start with +.',
        ];
    }
}
