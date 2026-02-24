<?php

namespace App\Http\Controllers;

use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Repositories\UnitRepository;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    public function __construct(
        protected UnitRepository $repository
    ) {}
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $units = $this->repository->getAllUnits();

        return response()->json([
            'success' => true,
            'data' => UnitResource::collection($units)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUnitRequest $request): JsonResponse
    {
        $unit = $this->repository->createUnit($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Unit created successfully',
            'data' => new CategoryResource($unit),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        $unit = $this->repository->updateUnit($unit, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Unit updated successfully',
            'data' => new CategoryResource($unit),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit): JsonResponse
    {
        if($unit->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Unit cannot be deleted because it is used by products.'
            ], 422);
        }

        $this->repository->deleteUnit($unit);

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully.'
        ]);
    }
}
