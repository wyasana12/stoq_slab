<?php

namespace App\Repositories;

use App\Models\Batch;
use Illuminate\Support\Facades\Auth;

class BatchRepository
{
    public function getAllPaginated(int $perPage = 10, array $filters)
    {
        $userId = Auth::user()->warehouse_id;

        $query = Batch::with(['product:id,name', 'receive.purchase.supplier:id,name'])
            ->select(['id', 'batch_code', 'product_id', 'warehouse_id', 'receiving_id', 'current_quantity', 'price', 'production_date', 'expired_date', 'created_at', 'updated_at', 'barcode']);


        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_code', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($productQuery) use ($search) {
                        $productQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        return $query->where('warehouse_id', $userId)
            ->latest()
            ->paginate($perPage);;
    }

    public function getById(Batch $batch): Batch
    {
        return $batch->load(['warehouse', 'receive.purchase.supplier', 'locations']);
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
