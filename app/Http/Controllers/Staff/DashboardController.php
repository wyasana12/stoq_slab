<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Models\StockDistributions;
use App\Models\StockReturns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $warehouseId = $request->user()->warehouse_id;

        if (!$warehouseId) {
            return response()->json([
                'success' => false,
                'message' => 'Staff tidak memiliki gudang yang ditugaskan.'
            ], 403);
        }

        $today = Carbon::today();

        // 1. Scan Hari Ini (Total Stock Mutations Hari Ini)
        $scanHariIni = StockMutations::where('warehouse_id', $warehouseId)
            ->whereDate('created_at', $today)
            ->count();

        // 2. Distribusi (Menunggu / Proses)
        $distribusi = StockDistributions::where('warehouse_id', $warehouseId)
            ->whereIn('status', ['DRAFT', 'PREPARING', 'SHIPPED']) // Sesuaikan dengan status yang relevan
            ->count();

        // 3. Return (Menunggu diproses / dari gudang ini)
        $return = StockReturns::where('warehouse_id', $warehouseId)
            ->whereIn('status', ['requested', 'approved']) // Sesuaikan
            ->count();

        // 4. Alert Expired (Dummy data using real products)
        // Fetches 3 batches from the current warehouse and pretends they are expiring soon
        $expiredAlertsRaw = Batch::with(['product', 'warehouse'])
            ->where('warehouse_id', $warehouseId)
            ->where('current_quantity', '>', 0)
            ->inRandomOrder()
            ->take(3)
            ->get();

        // If not enough batches, maybe fallback to products
        if ($expiredAlertsRaw->isEmpty()) {
            $expiredAlertsRaw = collect([]);
            // fallback dummy just in case
        }
        
        // We will transform them to look like expired alerts
        $expiredAlerts = $expiredAlertsRaw;
        // In the map function below, we will hardcode the expired date to look like dummy


        // 5. Tugas Saya (Distribusi Perlu Tindakan)
        // Misal distribusi yang approved/preparing
        $tasks = StockDistributions::with(['store', 'items'])
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', ['APPROVED', 'PREPARING'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($dist) {
                return [
                    'id' => $dist->id,
                    'distribution_code' => $dist->distribution_code,
                    'status' => $dist->status,
                    'destination' => $dist->store->name ?? '-',
                    'total_items' => $dist->items->count(),
                    'created_at' => $dist->created_at,
                ];
            });

        // 6. Scan Terkini
        $recentScans = StockMutations::with(['batch.product', 'warehouse'])
            ->where('warehouse_id', $warehouseId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($mut) {
                return [
                    'id' => $mut->id,
                    'product_name' => $mut->batch->product->name ?? 'Unknown',
                    'batch_code' => $mut->batch->batch_code ?? '-',
                    'quantity' => abs($mut->change_quantity),
                    'location' => $mut->batch->location ?? ($mut->warehouse->name ?? '-'),
                    'status' => $mut->status,
                    'created_at' => $mut->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'scan_hari_ini' => $scanHariIni,
                    'distribusi' => $distribusi,
                    'return' => $return,
                    'alert_expired' => $expiredAlerts->count(),
                ],
                'expired_alerts' => $expiredAlerts->map(function($batch, $index) {
                    // DUMMY DATA: Assign random small numbers for expired days
                    $dummyDays = [0, 2, 5][$index % 3]; 
                    
                    return [
                        'id' => $batch->id,
                        'product' => $batch->product->name ?? '-',
                        'batch' => $batch->batch_code,
                        'stock' => $batch->current_quantity . ' unit',
                        'expired' => $dummyDays <= 0 ? 'Sudah Expired' : $dummyDays . ' hari',
                        'location' => $batch->location ?? ($batch->warehouse->name ?? '-'),
                    ];
                }),
                'tasks' => $tasks,
                'recent_scans' => $recentScans,
            ]
        ]);
    }
}
