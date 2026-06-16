<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAndUpdateSupplierRequest;
use App\Http\Resources\Supplier\SupplierDetailResource;
use App\Http\Resources\Supplier\SupplierListResource;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $suppliers = Supplier::select('id', 'name', 'contact_person', 'phone_number', 'status', 'email', 'supplier_code')->get();

        $summary = [
            'total_suppliers'    => Supplier::count(),
            'active_suppliers'   => Supplier::where('status', true)->count(),
            'inactive_suppliers' => Supplier::where('status', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => SupplierListResource::collection($suppliers),

        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAndUpdateSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        $supplier->load('region');

        return response()->json([
            'success' => true,
            'message' => 'Supplier created successful.',
            'data' => new SupplierDetailResource($supplier)
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load('region');

        return response()->json([
            'success' => true,
            'data' => new SupplierDetailResource($supplier)
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreAndUpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        $supplier->load('region');

        return response()->json([
            'success' => true,
            'message' => 'Supplier updated successful.',
            'data' => new SupplierDetailResource($supplier),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->json([
            'success' => true,
            'message' => 'Supplier deleted successful.',
        ]);
    }

    public function dropdown(): JsonResponse
    {
        $suppliers = Supplier::select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => $suppliers
        ]);
    }
}
