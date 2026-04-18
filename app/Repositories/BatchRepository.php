<?php

namespace App\Repositories;

use App\Models\Batch;

class BatchRepository
{
    public function getAllPaginated(int $perPage = 10)
    {
        return Batch::with(['product:id,name'])
            ->select(['id', 'batch_code', 'product_id', 'current_quantity', 'price', 'expired_date', 'created_at', 'updated_at'])
            ->latest()
            ->paginate($perPage);
    }

    public function getById(Batch $batch): Batch
    {
        return $batch->load(['warehouse', 'product', 'receive.purchase.supplier']);
    }

    public function updateBarcode(Batch $batch, string $barcode): Batch
    {
        $batch->update([
            'barcode' => $barcode,
        ]);

        return $batch;
    }
}
