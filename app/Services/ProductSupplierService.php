<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSupplierItem;
use App\Models\Supplier;
use App\Repositories\ProductSupplierRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductSupplierService
{
    protected ProductSupplierRepository $productSupplierRepository;

    public function __construct(ProductSupplierRepository $productSupplierRepository)
    {
        $this->productSupplierRepository = $productSupplierRepository;
    }

    public function getAll()
    {
        return $this->productSupplierRepository->getAll();
    }

    public function create(array $data): Collection
    {
        return DB::transaction(function () use ($data) {
            $createdItems = [];
            $supplier = Supplier::find($data['supplier_id']);

            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);

                $spn = 'SPN-' . $supplier->supplier_code . '-' . $product->sku;

                $createdItems[] = $this->productSupplierRepository->create([
                    'supplier_id' => $supplier->id,
                    'product_id' => $product->id,
                    'SPN' => $spn,
                    'unit_price' => $item['unit_price'],
                    'min_order_quantity' => $item['min_order_quantity'],
                    'lead_time_days' => $item['lead_time_days'] ?? 0,
                    'return_limit_days' => $item['return_limit_days'] ?? 0,
                    'is_preferred' => $item['is_preferred'] ?? false,
                ]);
            }

            return collect($createdItems);
        });
    }

    public function update(string $supplierId, array $data): Collection
    {
        return DB::transaction(function () use ($supplierId, $data) {
            $requestProductIds = collect($data['items'])->pluck('product_id')->toArray();

            ProductSupplierItem::where('supplier_id', $supplierId)->whereNotIn('product_id', $requestProductIds)->delete();

            $updatedItems = [];

            $supplier = Supplier::find($supplierId);

            foreach ($data['items'] as $item) {
                $existingItem = ProductSupplierItem::where('supplier_id', $supplierId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                $payload = [
                    'unit_price' => $item['unit_price'],
                    'min_order_quantity' => $item['min_order_quantity'],
                    'lead_time_days' => $item['lead_time_days'] ?? 0,
                    'return_limit_days' => $item['return_limit_days'] ?? 0,
                    'is_preferred' => $item['is_preferred'] ?? false,
                ];

                if ($existingItem) {
                    $existingItem->update($payload);
                    $updatedItems[] = $existingItem;
                } else {
                    $product = Product::find($item['product_id']);

                    $payload['supplier_id'] = $supplierId;
                    $payload['product_id'] = $item['product_id'];
                    $payload['SPN'] = 'SPN-' . $supplier->supplier_code . '-' . $product->sku;

                    $updatedItems[] = $this->productSupplierRepository->create($payload);
                }
            }

            return collect($updatedItems);
        });
    }

    public function softDelete(ProductSupplierItem $productSupplier): void
    {
        $this->productSupplierRepository->softDelete($productSupplier);
    }

    public function deleteBySupplier(string $supplier): bool
    {
        return $this->productSupplierRepository->deleteBySupplier($supplier);
    }

    public function getTrashed()
    {
        return $this->productSupplierRepository->getTrashed();
    }

    public function restore(ProductSupplierItem $productSupplier): ProductSupplierItem
    {
        $this->productSupplierRepository->restore($productSupplier);

        return $productSupplier->fresh(['products.category:id,name', 'products.units:id,name', 'suppliers']);
    }

    public function forceDelete(ProductSupplierItem $productSupplier)
    {
        $this->productSupplierRepository->forceDelete($productSupplier);
    }
}
