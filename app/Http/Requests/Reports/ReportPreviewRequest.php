<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $templates = [
            'stock_current',
            'stock_movement',
            'stock_minimum',
        ];

        $allowedFields = [
            'product_code',
            'product_name',
            'category',
            'batch_code',
            'warehouse_name',
            'qty',
            'satuan',
            'nilai',
            'current_quantity',
            'production_date',
            'expired_date',
            'price',
            'movement_date',
            'movement_type',
        ];

        return [
            'template' => ['required', 'string', Rule::in($templates)],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', Rule::in($allowedFields)],
        ];
    }
}
