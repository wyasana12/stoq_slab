<?php

namespace App\Exports\Monitoring;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MonitoringExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(protected array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows)->map(function (array $row) {
            $row['activity_at'] = isset($row['activity_at']) && $row['activity_at'] !== null
                ? (string) $row['activity_at']
                : null;

            return $row;
        });
    }

    public function headings(): array
    {
        return [
            'activity_type',
            'warehouse_name',
            'batch_code',
            'product_name',
            'status',
            'quantity',
            'before_quantity',
            'after_quantity',
            'reference_type',
            'reference_id',
            'notes',
            'activity_at',
        ];
    }
}
