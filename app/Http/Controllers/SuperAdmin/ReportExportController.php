<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportExportRequest;
use App\Http\Requests\Reports\ReportPreviewRequest;
use App\Repositories\ReportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    protected ReportRepository $repository;

    public function __construct(ReportRepository $repository)
    {
        $this->repository = $repository;
    }

    public function preview(ReportPreviewRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $template = $filters['template'];
        $fields = $filters['fields'] ?? $this->repository->getDefaultFieldsForTemplate($template);
        $rows = $this->repository
            ->getReportRows($template, $filters)
            ->map(fn(array $row) => Arr::only($row, $fields))
            ->values();

        $categories = collect($rows)
            ->pluck('category')
            ->filter()
            ->unique()
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Preview report loaded successfully.',
            'meta' => [
                'template' => $template,
                'total_records' => $rows->count(),
                'total_items' => $rows->sum(fn($row) => $row['qty'] ?? $row['current_quantity'] ?? 0),
                'categories' => $categories,
                'fields' => $fields,
            ],
            'data' => $rows,
        ]);
    }

    public function export(ReportExportRequest $request)
    {
        $filters = $request->validated();
        $template = $filters['template'];
        $format = $filters['format'];

        $fields = $filters['fields'] ?? $this->repository->getDefaultFieldsForTemplate($template);
        $headings = $this->repository->getFieldHeadings($fields);

        $rows = $this->repository
            ->getReportRows($template, $filters)
            ->map(fn(array $row) => Arr::only($row, $fields))
            ->all();

        $filename = sprintf('report-%s-%s.%s', $template, now()->format('Ymd-His'), $format);

        return $format === 'csv'
            ? $this->streamCsv($filename, $headings, $rows)
            : Excel::download(new class($headings, $rows) implements FromArray, WithHeadings {
                private array $headings;
                private array $rows;

                public function __construct(array $headings, array $rows)
                {
                    $this->headings = $headings;
                    $this->rows = $rows;
                }

                public function array(): array
                {
                    return $this->rows;
                }

                public function headings(): array
                {
                    return $this->headings;
                }
            }, $filename);
    }

    protected function streamCsv(string $filename, array $headings, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }
}
