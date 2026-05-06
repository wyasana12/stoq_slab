<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAndUpdateWarehouseRequest;
use App\Http\Resources\Warehouse\WarehouseDetailResource;
use App\Http\Resources\Warehouse\WarehouseListResource;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $query = Warehouse::select('id', 'name', 'contact_person', 'phone_number', 'email', 'status', 'warehouse_code');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('warehouse_code', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', (bool) $request->status);
        }

        $warehouses = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WarehouseListResource::collection($warehouses)->response()->getData(true),
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
