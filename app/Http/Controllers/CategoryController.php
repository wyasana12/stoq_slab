<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAndUpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $categories = Category::paginate(10);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAndUpdateCategoryRequest $request)
    {
        $category = Category::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreAndUpdateCategoryRequest $request, Category $category)
    {
        $category = Category::updated($request->validated());

        
        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        //
    }
}
