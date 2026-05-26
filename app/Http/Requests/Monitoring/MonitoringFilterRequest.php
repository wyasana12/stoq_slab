<?php

namespace App\Http\Requests\Monitoring;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MonitoringFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'batch_id' => ['nullable', 'exists:batches,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:100'],
            'activity_type' => ['nullable', 'string', Rule::in([
                'stock_mutation',
                'distribution',
                'transfer',
                'restock',
                'return',
            ])],
            'scope' => ['nullable', 'string', Rule::in(['summary', 'batches', 'activities'])],
            'format' => ['nullable', 'string', Rule::in(['csv', 'xlsx'])],
        ];
    }
}
