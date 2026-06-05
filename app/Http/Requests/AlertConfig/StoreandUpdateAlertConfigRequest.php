<?php

namespace App\Http\Requests\AlertConfig;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreandUpdateAlertConfigRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $alertId = $this->route('alert')?->id;
        return [
            'name' => ['required', 'string', 'max:255'],
            'days_before' => ['required', 'integer', 'min:1', Rule::unique('alert_configs', 'days_before')->ignore($alertId)],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name alert is required.',
            'name.max' => 'Name characters cannot be more than 255',
            
            'days_before.required' => 'Days before alert is required.',
            'days_before.integer' => 'Days before alert must be a number.',
            'days_before.min' => 'Days before alert cannot be less than 1.',
            'days_before.unique' => 'Days before alert has already been taken.',

            'is_active.required' => 'Active alert is required.',
            'is_active.boolean' => 'Active alert must be true or false.',
        ];
    }
}
