<?php

namespace App\Repositories;

use App\Models\Product;

class ProductRepository
{
    public function getAll()
    {
        $query = Product::with([
            'category:id,name',
            'unit:id,symbol',
        ]);

        return $query->latest()->get();
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data)
    {
        return $product->update($data);
    }

    public function softDelete(Product $product): void
    {
        $product->delete();
    }

    public function getTrashedPaginated()
    {
        return Product::onlyTrashed()
            ->with(['category:id,name', 'unit:id,symbol'])
            ->latest('deleted_at');
    }

    public function restore(Product $product): void
    {
        $product->restore();
    }

    public function forceDelete(Product $product): void
    {
        $product->forceDelete();
    }
}
