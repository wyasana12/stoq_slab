<?php

namespace App\Repositories;

use App\Models\ProductReceiving;

class ProductReceivingRepository
{
    public function getAllPaginated(int $perPage = 10)
    {
        return ProductReceiving::with(['warehouse:id,name', 'purchase:id,po_code,order_date', 'user:id,name'])
                                ->select('id', 'receiving_code', 'receiving_date', 'status', 'purchase_id', 'warehouse_id', 'receiving_by')
                                ->latest()
                                ->paginate($perPage);   
    }

    public function getById(ProductReceiving $receive): ProductReceiving
    {
        return $receive->load(['warehouse', 'purchase', 'user', 'items.products']);    
    }
}
