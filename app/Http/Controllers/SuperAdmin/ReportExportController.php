<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportExportRequest;
use App\Http\Requests\Reports\ReportPreviewRequest;
use App\Models\ExportHistory;
use App\Repositories\ReportRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

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
            ->map(function (array $row) use ($fields) {
                $orderedRow = [];
                foreach ($fields as $field) {
                    $orderedRow[$field] = $row[$field] ?? null;
                }
                return $orderedRow;
            })
            ->all();

        $filename = sprintf('report-%s-%s.%s', $template, now()->format('Ymd-His'), $format);
        $filePath = 'reports/' . $filename;

        if ($format === 'csv') {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $headings);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            rewind($handle);
            Storage::disk('public')->put($filePath, stream_get_contents($handle));
            fclose($handle);
        } else {
            Excel::store(new class($headings, $rows) implements FromArray, WithHeadings {
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
            }, $filePath, 'public');
        }

        $templateNames = [
            'stock_current' => 'Laporan Stok Saat Ini',
            'stock_movement' => 'Laporan Pergerakan Stok',
            'stock_slow_moving' => 'Laporan Produk Slow Moving',
            'stock_critical' => 'Laporan Stok Kritis (DSS)',
            'stock_value' => 'Laporan Nilai Stok',
        ];

        ExportHistory::create([
            'user_id' => $request->user()->id ?? \App\Models\User::first()->id, // fallback for safety if no user
            'warehouse_id' => $filters['warehouse_id'] ?? null,
            'name' => $templateNames[$template] ?? 'Laporan Stok',
            'format' => $format,
            'file_path' => $filePath,
            'status' => 'completed',
        ]);

        return Storage::disk('public')->download($filePath, $filename);
    }

    public function history(Request $request): JsonResponse
    {
        $histories = ExportHistory::query()
            ->with(['warehouse'])
            ->latest()
            ->take(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $histories->map(fn($history) => [
                'id' => $history->id,
                'name' => $history->name,
                'date' => $history->created_at->toISOString(),
                'format' => $history->format,
                'warehouse' => $history->warehouse ? $history->warehouse->name : 'Semua Gudang',
                'status' => 'Selesai',
            ])
        ]);
    }

    public function downloadHistory($id)
    {
        $history = ExportHistory::findOrFail($id);
        if (!$history->file_path || !Storage::disk('public')->exists($history->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('public')->download($history->file_path);
    }
}
