<?php

namespace App\Services;

use App\Models\Product;
use App\Repositories\ProductRepository;
use Illuminate\Support\Facades\DB;

class ProductService
{
    protected $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getAllProducts(array $filters, int $productPage = 10)
    {
        return $this->productRepository->getAllPaginated($filters, $productPage);
    }

    public function createProduct(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = $this->productRepository->create([
                'sku' => $data['sku'],
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'unit_id' => $data['unit_id'],
            ]);

            if (!empty($data['items'])) {
                $this->productRepository->assignSupplier($product, $data['items']);
            }

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

            if (isset($data['items'])) {
                $this->productRepository->syncSuppliers($product, $data['items']);
            }

            return $product->fresh(['unit', 'category', 'productItems.supplier']);
        });
    }

    public function getProductDetail(Product $product): Product
    {
        return $this->productRepository->getById($product);
    }

    public function getTrashedProduct(int $perPage)
    {
        return $this->productRepository->getTrashedPaginated($perPage);    
    }

    public function softDeleteProduct(Product $product): void
    {
        $this->productRepository->softDelete($product);
    }

    public function restoreProduct(Product $product): Product
    {
        $this->productRepository->restore($product);

        return $product->fresh(['unit', 'category', 'productItems.supplier']);
    }

    public function forceDeleteProduct(Product $product)
    {
        $this->productRepository->forceDelete($product);    
    }
}
