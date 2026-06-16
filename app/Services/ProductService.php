<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Restock;
use App\Models\StockTransfers;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;

class ProductService
{
    protected ProductRepository $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getAllProducts()
    {
        return $this->productRepository->getAll();
    }

    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = $this->productRepository->create([
                'sku' => $data['sku'],
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'unit_id' => $data['unit_id'],
            ]);

            return $product;
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $this->productRepository->update($product, [
                'sku' => $data['sku'],
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'unit_id' => $data['unit_id'],
            ]);

            return $product->fresh(['unit', 'category']);
        });
    }

    public function getTrashedProduct()
    {
        return $this->productRepository->getTrashedPaginated();
    }

    public function softDeleteProduct(Product $product): void
    {
        $this->productRepository->softDelete($product);
    }

    public function restoreProduct(Product $product): Product
    {
        $this->productRepository->restore($product);

        return $product->fresh(['unit', 'category']);
    }

    public function forceDeleteProduct(Product $product)
    {
        $this->productRepository->forceDelete($product);
    }

    public function getProductsBySource(string $type, string $id)
    {
        return match ($type) {
            'purchase_order' => PurchaseOrder::with('items.product')->findOrFail($id)->items->map(fn($item) => [
                'id' => $item->product_id,
                'name' => $item->product->name,
                'quantity_approved' => $item->quantity_approved,
            ]),

            'transfer' => collect([StockTransfers::with('products')->findOrFail($id)])->map(fn($item) => [
                'id' => $item->product_id,
                'name' => $item->products->name,
                'quantity_ordered' => $item->approved_quantity,
            ]),

            'restock' => Restock::with('item.product')->findOrFail($id)->item->map(fn($item) => [
                'id' => $item->product_id,
                'name' => $item->product->name,
                'quantity_ordered' => $item->requested_quantity,
            ]),

            default => collect([]),
        };
    }
}
