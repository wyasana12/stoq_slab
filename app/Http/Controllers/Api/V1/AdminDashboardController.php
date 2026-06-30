<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Product;
use App\Models\Batch;
use App\Models\StockTransfers;
use App\Models\StockDisposal;
use App\Models\Restock;
use App\Models\StockDistributions;
use App\Models\ProductReceiving;
use App\Models\PurchaseOrder;
use App\Models\StockMutations;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $warehouseId = auth()->user()->warehouse_id;

        $pendingTransfers = StockTransfers::where('status', 'requested')
            ->when($warehouseId, fn($q) => $q->where(fn($sub) => $sub->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)));
        
        $pendingDisposals = StockDisposal::where('status', 'requested')
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId));
            
        $pendingRestocks = Restock::where('status', 'requested')
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId));
            
        $pendingDistributions = StockDistributions::where('status', 'waiting-approval')
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId));
            
        $pendingPurchaseOrders = PurchaseOrder::where('status', 'submitted')
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId));
        
        $totalPendingTasks = $pendingTransfers->count() + 
                             $pendingDisposals->count() + 
                             $pendingRestocks->count() + 
                             $pendingDistributions->count() +
                             $pendingPurchaseOrders->count();

        $stockAlerts = Product::select('id', 'name')
            ->when($warehouseId, fn($q) => $q->whereHas('batch', fn($sub) => $sub->where('warehouse_id', $warehouseId)))
            ->withSum(['batch' => fn($q) => $q->when($warehouseId, fn($sub) => $sub->where('warehouse_id', $warehouseId))], 'current_quantity')
            ->havingRaw('COALESCE(batch_sum_current_quantity, 0) <= 30')
            ->count();

        $todayTransfers = clone $pendingTransfers;
        $todayTransfers = StockTransfers::whereDate('created_at', Carbon::today())
            ->when($warehouseId, fn($q) => $q->where(fn($sub) => $sub->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)))->count();
            
        $todayDisposals = StockDisposal::whereDate('created_at', Carbon::today())
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->count();
            
        $todayReceivings = ProductReceiving::whereDate('created_at', Carbon::today())
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->whereHasMorph('receivable', [PurchaseOrder::class, StockTransfers::class], function ($query, $type) use ($warehouseId) {
                    if ($type === PurchaseOrder::class) {
                        $query->where('warehouse_id', $warehouseId);
                    } elseif ($type === StockTransfers::class) {
                        $query->where(fn($sub) => $sub->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId));
                    }
                });
            })->count();
            
        $todayDistributions = StockDistributions::whereDate('created_at', Carbon::today())
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->count();
            
        $todayTransactions = $todayTransfers + $todayDisposals + $todayReceivings + $todayDistributions;

        $tasks = collect();
        
        $transfers = $pendingTransfers->with('request', 'toWarehouse')->latest()->get();
        foreach($transfers as $t) {
            $tasks->push([
                'id' => 'TRF-'.$t->id,
                'title' => 'Transfer Produk',
                'subtitle' => 'Ke Gudang: ' . ($t->toWarehouse->name ?? 'Unknown'),
                'time_ago' => $t->created_at->diffForHumans(),
                'user' => $t->request->name ?? 'Admin',
                'created_at' => $t->created_at
            ]);
        }

        $disposals = $pendingDisposals->with('request')->latest()->get();
        foreach($disposals as $d) {
            $tasks->push([
                'id' => 'DSP-'.$d->id,
                'title' => 'Pemusnahan Barang',
                'subtitle' => 'Alasan: ' . $d->reason,
                'time_ago' => $d->created_at->diffForHumans(),
                'user' => $d->request->name ?? 'Admin',
                'created_at' => $d->created_at
            ]);
        }

        $restocks = $pendingRestocks->with('request', 'warehouse')->latest()->get();
        foreach($restocks as $r) {
            $tasks->push([
                'id' => 'RST-'.$r->id,
                'title' => 'Restock Produk',    
                'subtitle' => 'Lokasi: ' . ($r->warehouse->name ?? '-'),
                'time_ago' => $r->created_at->diffForHumans(),
                'user' => $r->request->name ?? 'Admin',
                'created_at' => $r->created_at
            ]);
        }

        $distributions = $pendingDistributions->with('request')->latest()->get();
        foreach($distributions as $dist) {
            $tasks->push([
                'id' => 'DST-'.$dist->id,
                'title' => 'Distribusi Produk',
                'subtitle' => 'Tujuan: ' . ($dist->outlet_name ?? 'Outlet'),
                'time_ago' => $dist->created_at->diffForHumans(),
                'user' => $dist->request->name ?? 'Admin',
                'created_at' => $dist->created_at
            ]);
        }

        $pos = $pendingPurchaseOrders->with('user', 'supplier')->latest()->get();
        foreach($pos as $po) {
            $tasks->push([
                'id' => 'PO-'.$po->id,
                'title' => 'Purchase Order',
                'subtitle' => 'Supplier: ' . ($po->supplier->name ?? 'Unknown'),
                'time_ago' => $po->created_at->diffForHumans(),
                'user' => $po->user->name ?? 'Admin',
                'created_at' => $po->created_at
            ]);
        }

        $pendingTasksList = $tasks->sortByDesc('created_at')->values()->map(function($item) {
            unset($item['created_at']);
            return $item;
        });

        $lowStockItems = Product::when($warehouseId, fn($q) => $q->whereHas('batch', fn($sub) => $sub->where('warehouse_id', $warehouseId)))
            ->withSum(['batch' => fn($q) => $q->when($warehouseId, fn($sub) => $sub->where('warehouse_id', $warehouseId))], 'current_quantity')
            ->havingRaw('COALESCE(batch_sum_current_quantity, 0) <= 30')
            
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'product_name' => $p->name,
                    'location' => 'Gudang',
                    'current_stock' => $p->batch_sum_current_quantity ?? 0,
                    'min_stock' => 30
                ];
            });

        $expiringProducts = Batch::with('product')
            ->where('expired_date', '<=', Carbon::now()->addDays(30))
            ->where('current_quantity', '>', 0)
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->orderBy('expired_date')
            
            ->get()
            ->map(function ($b) {
                return [
                    'id' => $b->id,
                    'product_name' => $b->product->name ?? 'Unknown',
                    'batch_no' => $b->batch_code,
                    'days_left' => (int) Carbon::now()->diffInDays(Carbon::parse($b->expired_date), false),
                    'expiry_date' => Carbon::parse($b->expired_date)->format('Y-m-d')
                ];
            });

        $expiringAlertsCount = Batch::where('expired_date', '<=', Carbon::now()->addDays(30))
            ->where('current_quantity', '>', 0)
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'pending_tasks' => $totalPendingTasks,
                    'recent_activities' => $todayTransactions,
                    'low_stock' => $stockAlerts,
                    'expiring' => $expiringAlertsCount,
                ],
                'pending_tasks' => $pendingTasksList,
                'low_stock_items' => $lowStockItems,
                'expiring_products' => $expiringProducts
            ]
        ]);
    }

    public function analytics(Request $request): JsonResponse
    {
        $warehouseId = auth()->user()->warehouse_id;
        $activities = collect();
        
        $recentReceivings = ProductReceiving::with('user')->latest()
            ->when($warehouseId, function ($q) use ($warehouseId) {
                $q->whereHasMorph('receivable', [PurchaseOrder::class, StockTransfers::class], function ($query, $type) use ($warehouseId) {
                    if ($type === PurchaseOrder::class) {
                        $query->where('warehouse_id', $warehouseId);
                    } elseif ($type === StockTransfers::class) {
                        $query->where(fn($sub) => $sub->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId));
                    }
                });
            })->get();
            
        foreach($recentReceivings as $r) {
            $activities->push([
                'id' => 'RCV-'.$r->id,
                'title' => 'Penerimaan Barang',
                'description' => 'Sistem Penerimaan (Masuk)',
                'time_ago' => $r->created_at->diffForHumans(),
                'user' => $r->user->name ?? 'Admin',
                'created_at' => $r->created_at
            ]);
        }

        $recentTransfers = StockTransfers::with('request', 'toWarehouse')->latest()
            ->when($warehouseId, fn($q) => $q->where(fn($sub) => $sub->where('from_warehouse_id', $warehouseId)->orWhere('to_warehouse_id', $warehouseId)))->get();
            
        foreach($recentTransfers as $t) {
            $activities->push([
                'id' => 'TRF-'.$t->id,
                'title' => 'Transfer Produk',
                'description' => 'Ke Gudang: ' . ($t->toWarehouse->name ?? '-'),
                'time_ago' => $t->created_at->diffForHumans(),
                'user' => $t->request->name ?? 'Admin',
                'created_at' => $t->created_at
            ]);
        }

        $recentDistributions = StockDistributions::with('request')->latest()
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->get();
            
        foreach($recentDistributions as $d) {
            $activities->push([
                'id' => 'DST-'.$d->id,
                'title' => 'Distribusi Produk',
                'description' => 'Tujuan: ' . ($d->outlet_name ?? '-'),
                'time_ago' => $d->created_at->diffForHumans(),
                'user' => $d->request->name ?? 'Admin',
                'created_at' => $d->created_at
            ]);
        }

        $recentDisposals = StockDisposal::with('request')->latest()
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))->get();
            
        foreach($recentDisposals as $d) {
            $activities->push([
                'id' => 'DSP-'.$d->id,
                'title' => 'Pemusnahan Barang',
                'description' => 'Alasan: ' . $d->reason,
                'time_ago' => $d->created_at->diffForHumans(),
                'user' => $d->request->name ?? 'Admin',
                'created_at' => $d->created_at
            ]);
        }

        $recentActivities = $activities->sortByDesc('created_at')->values()->map(function($item) {
            unset($item['created_at']);
            return $item;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'recent_activities' => $recentActivities
            ]
        ]);
    }
}
