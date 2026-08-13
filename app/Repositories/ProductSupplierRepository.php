<?php

namespace App\Repositories;

use App\Models\ProductSupplierItem;

class ProductSupplierRepository
{
    public function getAll()
    {
        return ProductSupplierItem::with(['products.category:id,name', 'products.unit:id,name', 'suppliers'])->get();
    }

    public function create(array $data): ProductSupplierItem
    {
        return ProductSupplierItem::updateOrCreate(
            [
                'supplier_id' => $data['supplier_id'],
                'product_id'  => $data['product_id'],
            ],
            $data
        );
    }
    
    public function update(ProductSupplierItem $productSupplier, array $data)
    {
        return $productSupplier->update($data);
    }

    public function softDelete(ProductSupplierItem $productSupplier): void
    {
        $productSupplier->delete();
    }

    public function deleteBySupplier(string $supplier): bool
    {
        return ProductSupplierItem::where('supplier_id', $supplier)->delete();    
    }

    public function getTrashed()
    {
        return ProductSupplierItem::onlyTrashed()->with(['products.category:id,name', 'products.units:id,name', 'suppliers'])->latest('deleted_at')->get();
    }

    public function restore(ProductSupplierItem $productSupplier): void
    {
        $productSupplier->restore();
    }

    public function forceDelete(ProductSupplierItem $productSupplier): void
    {
        $productSupplier->forceDelete();
    }
}
