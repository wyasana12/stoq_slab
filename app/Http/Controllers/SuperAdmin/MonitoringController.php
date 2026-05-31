<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoring\MonitoringFilterRequest;
use App\Http\Resources\Monitoring\BatchDetailResource;
use App\Http\Resources\Monitoring\StockMonitoringResource;
use App\Http\Resources\Monitoring\WarehouseActivityResource;
use App\Repositories\MonitoringRepository;
use App\Exports\Monitoring\MonitoringExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitoringController extends Controller
{
    protected MonitoringRepository $repository;

    public function __construct()
    {
        $this->repository = app('App\\Repositories\\MonitoringRepository');
    }

    public function summary(MonitoringFilterRequest $request): JsonResponse
    {
        $filters = $this->filters($request);
        $summary = $this->repository->getWarehouseSummary($filters);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse summary retrieved successfully.',
            'meta' => [
                'scope' => 'summary',
                'count' => $summary->count(),
                'filters' => $filters,
            ],
            'data' => StockMonitoringResource::collection($summary),
        ]);
    }

    public function batches(MonitoringFilterRequest $request): JsonResponse
    {
        $filters = $this->filters($request);
        $batches = $this->repository->getBatchDetails($filters);

        return response()->json([
            'success' => true,
            'message' => 'Batch detail monitoring retrieved successfully.',
            'meta' => [
                'scope' => 'batches',
                'count' => $batches->count(),
                'filters' => $filters,
            ],
            'data' => BatchDetailResource::collection($batches),
        ]);
    }

    public function activities(MonitoringFilterRequest $request): JsonResponse
    {
        $filters = $this->filters($request);
        $activities = $this->repository->getActivityLog($filters);

        return response()->json([
            'success' => true,
            'message' => 'Warehouse activity log retrieved successfully.',
            'meta' => [
                'scope' => 'activities',
                'count' => $activities->count(),
                'filters' => $filters,
            ],
            'data' => WarehouseActivityResource::collection($activities),
        ]);
    }

    public function exportCsv(MonitoringFilterRequest $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $rows = $this->repository->exportCsvRows($filters);
        $filename = 'monitoring-' . ($filters['scope'] ?? 'activities') . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
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
            ]);

            foreach ($rows as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function exportXlsx(MonitoringFilterRequest $request)
    {
        $filters = $this->filters($request);
        $rows = $this->repository->exportCsvRows($filters);
        $filename = 'monitoring-' . ($filters['scope'] ?? 'activities') . '-' . now()->format('Ymd-His') . '.xlsx';

        return Excel::download(new MonitoringExport($rows), $filename);
    }

    private function filters(MonitoringFilterRequest $request): array
    {
        return $request->validated();
    }
}
