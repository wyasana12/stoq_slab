<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class StoreAndUpdateUser extends FormRequest
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
        $userId = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:8', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['required', 'email', 'string', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone_number' => ['required', 'string', Rule::unique('users', 'phone_number')->ignore($userId), 'regex:/^\+?[0-9]{7,15}$/'],
            'region_id' => ['required', 'exists:region,id'],
            'street' => ['required', 'string', 'max:255'],
            'postal_code' => ['required', 'digits:5'],
            'birth_date' => ['required', 'date', 'before:today'],
            'password' => [$userId ? 'nullable' : 'required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role_id' => ['required', 'exists:roles,id'],
            'warehouse_id' => ['nullable', Rule::requiredIf(function () {
                if(!$this->role_id) {
                    return false;
                }
                $role = Role::find($this->role_id);
                return $role && $role->name !== 'super-admin';
            }), 'exists:warehouses,id'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'User name is required.',
            'name.max' => 'User name must be at most 255 characters.',

            'username.required' => 'User username is required.',
            'username.min' => 'User username must be at least 8 characters',
            'username.unique' => 'User username has already been taken',
            
            'email.required' => 'User email is required.',
            'email.email' => 'User email type is email.',
            'email.max' => 'User email must be at most 255 characters.',
            'email.unique' => 'User email has already been taken.',

            'phone_number.required' => 'User phone number is required.',
            'phone_number.unique' => 'User phone number has already been taken.',
            'phone_number.regex' => 'User phone number must be 7-15 digits and may optionally start with +.',

            'region_id.required' => 'User region is required.',
            'region_id.exists' => 'User region is invalid.',

            'street.required' => 'User street is required.',
            'street.max' => 'User street must be at most 255 characters.',

            'postal_code.required' => 'User postal code is required.',
            'postal_code.digits' => 'User postal code must be exactly 5 digits.',

            'birth_date.required' => 'User birth date is required.',
            'birth_date.date' => 'User birth date must be a valid date.',
            'birth_date.before' => 'User birth date cannot be a today or future day.',

            'password.required' => 'User password is required.',
            'password.confirmed' => 'The user password confirmation does not match.',
            
            'role_id.required' => 'Role is required.',
            'role_id.exists' => 'This selected role is invalid.',

            'warehouse_id.required' => 'Warehouse is required.',
            'warehouse_id.exists' => 'This selected warehouse is invalid.',
        ];
    }
}
