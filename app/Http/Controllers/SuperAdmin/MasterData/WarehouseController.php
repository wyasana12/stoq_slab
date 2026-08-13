<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAndUpdateWarehouseRequest;
use App\Http\Resources\Warehouse\WarehouseDetailResource;
use App\Http\Resources\Warehouse\WarehouseListResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::select('id', 'name', 'contact_person', 'phone_number', 'email', 'status', 'warehouse_code')->get();

        $summary = [
            'total_warehouses'    => Warehouse::count(),
            'active_warehouses'   => Warehouse::where('status', true)->count(),
            'inactive_warehouses' => Warehouse::where('status', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => WarehouseListResource::collection($warehouses),
        ], 200);
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
        $oldStatus = $warehouse->status;
        
        $warehouse->update($request->validated());

        $warehouse->load('region');

        if ($oldStatus == true && $warehouse->status == false) {
            $warehouse->users()->each(function ($user) {
                $user->tokens()->delete();
                Cache::forget("auth_user_{$user->id}");
            });
        }

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

    public function dropdown(): JsonResponse
    {
        $warehouses = Warehouse::select('id', 'name')->where('status', true)->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses
        ]);
    }

    public function filter(): JsonResponse
    {
        $warehouses = Warehouse::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $warehouses
        ]);
    }
}
