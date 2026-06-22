<?php

namespace App\Http\Requests\Distribution;

use App\Enums\DistributionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDistributionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_column(DistributionStatus::cases(), 'value')),
            ],
            'confirmed_by' => 'nullable|exists:users,id',
            'notes' => 'nullable|string|max:255',

            'items' => 'required_if:status,' . DistributionStatus::APPROVED->value . ',' . DistributionStatus::COMPLETED->value . '|array|min:1',
            'items.*.id' => 'required_with:items|exists:stock_distribution_items,id',
            'items.*.approved_quantity' => 'required_if:status,' . DistributionStatus::APPROVED->value . '|integer|min:0',
            'items.*.received_quantity' => 'required_if:status,' . DistributionStatus::COMPLETED->value . '|integer|min:0',
            'items.*.damaged_quantity' => 'required_if:status,' . DistributionStatus::COMPLETED->value . '|integer|min:0',
        ];
    }
}
