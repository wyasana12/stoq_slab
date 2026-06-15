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
            ->select(['id', 'batch_code', 'product_id', 'receiving_id', 'current_quantity', 'price', 'production_date', 'expired_date']);

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

    public function getSelectedForPrint(array $batchIds)
    {
        $warehouseId = Auth::user()->warehouse_id;

        return Batch::with([
            //'product:id,name',
            'product.unit:id,name,symbol'
        ])->whereIn('id', $batchIds)->where('warehouse_id', $warehouseId)->get();
    }
}
