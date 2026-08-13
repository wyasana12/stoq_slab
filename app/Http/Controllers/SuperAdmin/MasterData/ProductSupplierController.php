<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreAndUpdateProductSupplierRequest;
use App\Http\Resources\ProductSupplier\ProductSupplierListResource;
use App\Models\ProductSupplierItem;
use App\Services\ProductSupplierService;
use Illuminate\Http\JsonResponse;

class ProductSupplierController extends Controller
{
    protected ProductSupplierService $productSupplierService;

    public function __construct(ProductSupplierService $productSupplierService)
    {
        $this->productSupplierService = $productSupplierService;
    }

    public function index(): JsonResponse
    {
        try {
            $allProductSuppliers = $this->productSupplierService->getAll();

            return response()->json([
                'success' => true,
                'data' => new ProductSupplierListResource($allProductSuppliers),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to fetch Product Supplier.',
                'error' => $err->getMessage()
            ], 500);
        }
    }

    public function store(StoreAndUpdateProductSupplierRequest $request): JsonResponse
    {
        try {
            $this->productSupplierService->create($request->validated());

            return response()->json([
                'success' => true,
                'messages' => 'Product Supplier created successful.',
            ], 204);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to create Product Supplier.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function update(string $supplierId, StoreAndUpdateProductSupplierRequest $request): JsonResponse
    {
        try {
            $this->productSupplierService->update($supplierId, $request->validated());

            return response()->json([
                'success' => true,
                'messages' => 'Product Supplier updated succesful.',
            ], 204);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to update Product Supplier.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function destroy(ProductSupplierItem $productSupplier): JsonResponse
    {
        try {
            $this->productSupplierService->softDelete($productSupplier);

            return response()->json([
                'success' => true,
                'messages' => 'Product Supplier deleted successful.',
            ], 204);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to deleted Product Supplier.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function destroyBySupplier(string $supplier): JsonResponse
    {
        try {
            $this->productSupplierService->deleteBySupplier($supplier);

            return response()->json([
                'success' => true,
                'messages' => 'Product Supplier and all related items deleted successfully.'
            ], 204);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to deleted Product Items by Supplier.',
                'error' => $err->getMessage(),
            ], 500);    
        }
    }

    public function trashed(): JsonResponse
    {
        try {
            $productSupplierTrashed = $this->productSupplierService->getTrashed();

            return response()->json([
                'success' => true,
                'data' => ProductSupplierListResource::collection($productSupplierTrashed),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to fetch Product Supplier.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function restore(ProductSupplierItem $productSupplier): JsonResponse
    {
        try {
            $this->productSupplierService->restore($productSupplier);

            return response()->json([
                'success' => true,
                'messages' => 'Product Supplier restored successful.',
            ], 204);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to restore Product Supplier.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function force(ProductSupplierItem $productSupplier): JsonResponse
    {
        try {
            $this->productSupplierService->forceDelete($productSupplier);

            return response()->json([
                'success' => true,
                'messages' => 'Product Supplier force deleted succesful.',
            ], 204);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to force delete Product Supplier.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }
}
