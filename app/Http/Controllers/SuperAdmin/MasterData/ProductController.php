<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreAndUpdateProductRequest;
use App\Http\Resources\Product\ProductDetailResource;
use App\Http\Resources\Product\ProductListResource;
use App\Http\Resources\Product\ProductTrashResource;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'category_ids', 'supplier_ids']);
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $allProducts = $this->productService->getAllProducts($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => ProductListResource::collection($allProducts)->response()->getData(true),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve all products.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAndUpdateProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->createProduct($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Product created successful.',
                'data' => new ProductDetailResource($product),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to create product.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        try {
            $productDetail = $this->productService->getProductDetail($product);

            return response()->json([
                'success' => true,
                'data' => new ProductDetailResource($productDetail),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve product details.',
                'error' => $err->getMessage(),
            ], 500);
        }
        $product->load(['category', 'unit']);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreAndUpdateProductRequest $request, Product $product)
    {
        try {
            $updateProduct = $this->productService->updateProduct(
                $product,
                $request->validated(),
            );

            return response()->json([
                'success' => true,
                'message' => 'Product updated succesful.',
                'data' => new ProductDetailResource($updateProduct)
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to update product.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        try {
            $this->productService->softDeleteProduct($product);

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successful.'
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deleted product.',
                'error' => $err->getMessage(),
            ]);
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $trashedProducts = $this->productService->getTrashedProduct($perPage);

            return response()->json([
                'success' => true,
                'data' => ProductTrashResource::collection($trashedProducts),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve trashed products.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function restore(Product $product): JsonResponse
    {
        try {
            $restoredProduct = $this->productService->restoreProduct($product);

            return response()->json([
                'success' => true,
                'data' => new ProductDetailResource($restoredProduct),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to restore product.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function forceDestroy(Product $product): JsonResponse
    {
        try {
            $this->productService->forceDeleteProduct($product);

            return response()->json([
                'success' => true,
                'message' => 'Product force deleted successful.'
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to force deleted product.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function getProductbyPurchaseOrder(PurchaseOrder $purchase): JsonResponse
    {
        $products = $purchase->products()->select('products.id', 'products.sku', 'products.name')->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function getProductBySupplier(Supplier $supplier): JsonResponse
    {
        $products = $supplier->products()
            ->select('products.id', 'products.sku', 'products.name', 'products.category_id', 'products.unit_id')
            ->with(['category:id,name', 'unit:id,name,symbol'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
}
