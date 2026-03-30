<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:8', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['required', 'email', 'string', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => [$isUpdate ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role_id' => [$isUpdate ? 'nullable' : 'required', 'exists:roles,id'],
            'warehouse_id' => [$isUpdate ? 'nullable' : 'required', 'exists:warehouses,id'],
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

            'password.required' => 'User password is required.',
            'password.min' => 'User password must be at least 8 characters.',
            'password.confirmed' => 'The user password confirmation does not match.',
            
            'role_id.required' => 'Role is required.',
            'role_id.exists' => 'This selected role is invalid.',

            'warehouse_id.required' => 'Warehouse is required.',
            'warehouse_id.exists' => 'This selected warehouse is invalid.',
        ];
    }
}
