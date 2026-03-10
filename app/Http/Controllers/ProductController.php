<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\Product\ProductDetailResource;
use App\Http\Resources\Product\ProductListResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $products = Product::with('category')->select('id', 'sku', 'name', 'category_id')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => ProductListResource::collection($products),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        $product->load(['category', 'unit']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successful.',
            'data' => new ProductDetailResource($product),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product): JsonResponse
    {
        $product->load(['category', 'unit']);

        return response()->json([
            'success' => true,
            'data' => new ProductDetailResource($product),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $product->update($request->validated());

        $product->load(['category', 'unit']);

        return response()->json([
            'success' => true,
            'message' => 'Product updated succesful.',
            'data' => new ProductDetailResource($product)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successful.'
        ]);
    }
}
