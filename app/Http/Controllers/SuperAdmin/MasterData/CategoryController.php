<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAndUpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(
        protected CategoryRepository $repository
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $categories = $this->repository->getAllCategories($perPage);

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories)->response()->getData(true),
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAndUpdateCategoryRequest $request)
    {
        $category = $this->repository->createCategory($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreAndUpdateCategoryRequest $request, Category $category)
    {
        $category = $this->repository->updateCategory($category, $request->validated());


        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Category cannot be deleted because it is used by products.'
            ], 422);
        }

        $this->repository->deleteCategory($category);

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.'
        ]);
    }
}
