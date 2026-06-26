<?php

namespace App\Repositories;

use App\Models\Batch;
use App\Models\PurchaseOrder;
use App\Models\Restock;
use App\Models\StockTransfers;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

class BatchRepository
{
    public function getSummary()
    {
        $warehouseId = Auth::user()->warehouse_id;
        
        $query = Batch::where('warehouse_id', $warehouseId);

        $lowStock = 10;

        return [
            'total_batch' => (clone $query)->count(),
            
            'available_batch' => (clone $query)
                ->where('current_quantity', '>', 0)
                ->count(),
                
            'empty_batch' => (clone $query)
                ->where('current_quantity', '=', 0)
                ->count(),
                
            'low_stock_batch' => (clone $query)
                ->where('current_quantity', '=', 0)
                ->where('current_quantity', '<=', $lowStock)
                ->count(),
                
            'expiring_soon_batch' => (clone $query)
                ->whereNotNull('expired_date')
                ->whereDate('expired_date', '>=', now())
                ->whereDate('expired_date', '<=', now()->addDays(30))
                ->count(),
        ];
    }
    public function getAll()
    {
        $userId = Auth::user()->warehouse_id;

        $query = Batch::with([
            'product:id,name',
            'receive.receivable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    PurchaseOrder::class => ['supplier:id,name'],
                    StockTransfers::class => ['fromWarehouse:id,name'],
                    Restock::class => ['supplier:id,name'],
                ]);
            }
        ])
            ->select(['id', 'batch_code', 'product_id', 'receiving_id', 'current_quantity', 'initial_quantity', 'price', 'production_date', 'expired_date', 'condition']);

        return $query->where('warehouse_id', $userId)
            ->latest()
            ->get();;
    }

    public function getById(Batch $batch): Batch
    {
        return $batch->load([
            'warehouse',
            'product:id,name',
            'locations',
            'receive.receivable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    PurchaseOrder::class => ['supplier:id,name'],
                    StockTransfers::class => ['fromWarehouse:id,name'],
                    Restock::class => ['supplier:id,name'],
                ]);
            }
        ]);
    }

    public function create(array $data): Batch
    {
        return Batch::create($data);
    }

    public function updateBarcode(Batch $batch, string $barcode): Batch
    {
        $batch->update([
            'barcode' => $barcode,
        ]);

        return $batch->fresh();
    }

    public function destroy(Batch $batch)
    {
        return $batch->delete();
    }

    public function getSelectedForPrint(array $batchIds)
    {
        $warehouseId = Auth::user()->warehouse_id;

        return Batch::with([
            //'product:id,name',
            'product.unit:id,name,symbol'
        ])->whereIn('id', $batchIds)->where('warehouse_id', $warehouseId)->get();
    }
}
