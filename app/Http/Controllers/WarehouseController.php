<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAndUpdateWarehouseRequest;
use App\Http\Resources\Warehouse\WarehouseDetailResource;
use App\Http\Resources\Warehouse\WarehouseListResource;
use App\Models\Warehouse;
use Symfony\Component\HttpFoundation\JsonResponse;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::select('id', 'name', 'contact_person', 'phone_number', 'status')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => WarehouseListResource::collection($warehouses),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAndUpdateWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());

        $warehouse->load('region');

        return response()->json([
            'success' => true,
            'message' => 'Warehouse created successful.',
            'data' => new WarehouseDetailResource($warehouse),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load('region');

        return response()->json([
            'success' => true,
            'data' => new WarehouseDetailResource($warehouse),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreAndUpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());

        $warehouse->load('region');

        return response()->json([
            'success' => true,
            'message' => 'Warehouse updated successful.',
            'data' => new WarehouseDetailResource($warehouse),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return response()->json([
            'success' => true,
            'message' => 'Warehouse deleted successful.',
        ]);
    }
}
