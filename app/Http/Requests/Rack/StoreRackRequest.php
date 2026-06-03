<?php

namespace App\Http\Requests\Rack;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'rack_code' => ['required', 'string', 'max:20'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'status' => ['required', Rule::in(['AVAILABLE', 'FULL', 'INACTIVE', 'MAINTENANCE'])],

            'levels' => ['required', 'numeric', 'min:1'],
            'bins_per_level' => ['required', 'numeric', 'min:1'],

            'capacity_unit' => ['required', Rule::in(['PCS', 'BOX', 'CARTON', 'PALLET'])],
            'capacity' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function messages()
    {
        return [
            'rack_code.required' => 'Rack Code is required.',
            'rack_code.max' => 'Rack Code cannot be more than 20 characters.',
            'warehouse_id.required' => 'Warehouse is required',
            'warehouse_id.exists' => 'Selected warehouse are invalid or do not exists.', 
            'status.required' => 'Status is required.',
            'status.in' => 'This selected status is invalid for request.',
            
            'levels.required' => 'Levels Rack is required.',
            'levels.numeric' => 'Levels Rack must be a number.',
            'levels.min' => 'Levels Rack cannot be less than 1.',
            'bins_per_level.required' => 'Bins per Level Rack is required.',
            'bins_per_level.numeric' => 'Bins per Level Rack must be a number.',
            'bins_per_level.min' => 'Bins per Level Rack cannot be less than 1.',

            'capacity_unit.required' => 'Capacity Unit is required.', 
            'capacity_unit.in' => 'This selected capacity unit is invalid for request.',
            'capacity.required' => 'Capacity is required.',
            'capacity.numeric' => 'Capacity must be a number.',
            'capacity.min' => 'Capacity cannot be less than 0.',
        ];
    }
}
