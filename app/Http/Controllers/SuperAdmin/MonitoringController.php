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
use Illuminate\Support\Facades\Auth;
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

    // Monitoring Admin Rajwa

    public function dashboard(MonitoringFilterRequest $request): JsonResponse
    {
        $filters = $this->filters($request);

        $dashboardSummary = $this->repository->getDashboardSummary($filters);
        $chartData = $this->repository->getChartData($filters);

        // Kita bongkar dulu isinya jika getDashboardSummary mengembalikan array langsung
        $stockStatus = $dashboardSummary['stock_status'] ?? $dashboardSummary;

        return response()->json([
            'success' => true,
            'message' => 'Dashboard summary and chart data retrieved successfully.',
            'data' => [
                'total_sku'       => $dashboardSummary['total_sku'] ?? 0,
                'stock_status'    => $stockStatus,
                'today_stats'     => $dashboardSummary['today_stats'] ?? [],
                'rack_capacities' => $dashboardSummary['rack_capacities'] ?? [],
                'activities'      => $chartData['activities'] ?? [],
                'warehouses'      => $chartData['warehouses'] ?? [],
            ],
        ]);
    }
    public function alerts(MonitoringFilterRequest $request): JsonResponse
    {
        $filters = $this->filters($request);
        $alerts = $this->repository->getLowStockAlerts($filters);

        return response()->json([
            'success' => true,
            'message' => 'Low stock alerts retrieved successfully.',
            'meta' => [
                'count' => $alerts->count(),
            ],
            'data' => $alerts,
        ]);
    }

    private function filters(MonitoringFilterRequest $request): array
    {
        $filters = $request->validated();

        // Auto-filter to assigned warehouse for admin users
        $userWarehouseId = Auth::user()?->warehouse_id;
        if ($userWarehouseId) {
            $filters['warehouse_id'] = $userWarehouseId;
        }

        return $filters;
    }
}
