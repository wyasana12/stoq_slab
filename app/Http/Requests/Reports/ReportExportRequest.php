<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportExportRequest extends FormRequest
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
            'stock_slow_moving',
            'batch_expiry',
            'stock_minimum',
            'stock_critical',
            'stock_value',
            'restock_history',
            'distribution_history',
        ];

        $allowedFields = [
            'product_code',
            'product_name',
            'category',
            'batch_code',
            'warehouse_name',
            'current_quantity',
            'production_date',
            'expired_date',
            'price',
            'total_value',
            'movement_date',
            'movement_type',
            'from_warehouse',
            'to_warehouse',
            'requested_by',
            'confirmed_by',
            'restock_date',
            'distribution_date',
            'status',
        ];

        return [
            'template' => ['nullable', 'string', Rule::in($templates)],
            'format' => ['nullable', 'string', Rule::in(['csv', 'xlsx'])],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'min_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', Rule::in($allowedFields)],
            'include_summary' => ['nullable', 'boolean'],
            'include_charts' => ['nullable', 'boolean'],
        ];
    }
}
