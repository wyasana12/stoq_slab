<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\ProductSupplierItem;

class ProductRepository
{
    public function getAllPaginated(array $filters, int $perPage = 10)
    {
        $query = ProductSupplierItem::with([
            'product.category:id,name',
            'product.unit:id,symbol',
            'supplier:id,name'
        ]);

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['category_ids']) && is_array($filters['category_ids'])) {
            $query->whereHas('product', function ($q) use ($filters) {
                $q->whereIn('category_id', $filters['category_ids']);
            });
        }

        if (!empty($filters['supplier_ids']) && is_array($filters['supplier_ids'])) {
            $query->whereIn('supplier_id', $filters['supplier_ids']);
        }

        return $query->latest()->paginate($perPage)->withQueryString();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function assignSupplier(Product $product, array $suppliers): void
    {
        foreach ($suppliers as $supplier) {
            $product->productItems()->create([
                'supplier_id' => $supplier['supplier_id'],
                'unit_price' => $supplier['unit_price'],
                'min_order_quantity' => $supplier['min_order_quantity'],
                'lead_time_days' => $supplier['lead_time_days'],
                'return_limit_days' => $supplier['return_limit_days'],
                'is_preferred' => $supplier['is_preferred'],
            ]);
        }
    }

    public function update(Product $product, array $data)
    {
        return $product->update($data);
    }

    public function deleteItems(Product $product): void
    {
        $product->productItems()->forceDelete();
    }

    public function getById(Product $product): Product
    {
        return $product->load(['category', 'unit', 'productItems.supplier']);
    }

    public function softDelete(Product $product): void
    {
        $product->productItems()->delete();
        $product->delete();
    }

    public function getTrashedPaginated(int $perPage = 10)
    {
        return Product::onlyTrashed()
            ->with(['category:id,name', 'unit:id,symbol', 'productItems.supplier:id,name'])
            ->latest('deleted_at')
            ->paginate($perPage);
    }

    public function restore(Product $product): void
    {
        $product->restore();
    }

    public function forceDelete(Product $product): void
    {
        $product->productItems()->forceDelete();
        $product->forceDelete();
    }
}
