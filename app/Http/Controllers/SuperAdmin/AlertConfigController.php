<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AlertConfig\StoreandUpdateAlertConfigRequest;
use App\Models\AlertConfig;
use App\Models\AlertLog;
use App\Services\AlertStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertConfigController extends Controller
{
    public function __construct(protected AlertStockService $alertStockService) {}

    public function index(): JsonResponse
    {
        $configs = AlertConfig::orderBy('days_before', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    public function store(StoreandUpdateAlertConfigRequest $request): JsonResponse
    {
        $config = AlertConfig::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Alert config created successful.',
            'data' => $config
        ]);
    }

    public function update(StoreandUpdateAlertConfigRequest $request, AlertConfig $alert): JsonResponse {
        $alert->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Alert config updated successful.',
            'data' => $alert,
        ]);
    }

    public function destroy(AlertConfig $alert): JsonResponse {
        $alert->delete();

        return response()->json([
            'success' => true,
            'message' => 'Alert config deleted succesful.',
        ]);
    }

    /**
     * Jalankan pengecekan alert stok mendekati habis / kadaluarsa.
     * Menggunakan AlertStockService untuk cek semua batch di semua gudang.
     */
    public function checkAlerts(Request $request): JsonResponse
    {
        $warehouseId = $request->query('warehouse_id');

        $result = $this->alertStockService->checkAndSendAlerts($warehouseId);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'triggered' => $result['triggered'],
        ]);
    }

    /**
     * Ambil log alert yang sudah dikirim (untuk ditampilkan di UI monitoring).
     */
    public function logs(Request $request): JsonResponse
    {
        $query = AlertLog::with(['batch.product', 'batch.warehouse', 'config'])
            ->orderBy('created_at', 'desc');

        if ($request->query('warehouse_id')) {
            $query->where('warehouse_id', $request->query('warehouse_id'));
        }

        $logs = $query->limit(100)->get()->map(function ($log) {
            return [
                'id'           => $log->id,
                'title'        => $log->title,
                'message'      => $log->message,
                'batch_code'   => $log->batch?->batch_code,
                'product_name' => $log->batch?->product?->name,
                'warehouse'    => $log->batch?->warehouse?->name,
                'config_name'  => $log->config?->name,
                'created_at'   => $log->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $logs,
        ]);
    }
}

