<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAndUpdateStoreRequest;
use App\Http\Resources\Store\StoreDetailResource;
use App\Http\Resources\Store\StoreListResource;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = Store::with('warehouse:id,name')->select('id', 'name', 'contact_person', 'phone_number', 'email', 'status', 'store_code', 'warehouse_id')->get();

        $summary = [
            'total_stores'    => Store::count(),
            'active_stores'   => Store::where('status', true)->count(),
            'inactive_stores' => Store::where('status', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => StoreListResource::collection($stores)
        ], 200);
    }

    public function store(StoreAndUpdateStoreRequest $request): JsonResponse
    {
        $store = Store::create($request->validated());

        $store->load(['region', 'warehouse']);

        return response()->json([
            'success' => true,
            'messages' => 'Store created successful.',
            'data' => new StoreDetailResource($store)
        ], 201);
    }

    public function show(Store $store): JsonResponse
    {
        $store->load(['region', 'warehouse']);

        return response()->json([
            'success' => true,
            'data' => new StoreDetailResource($store),
        ], 200);
    }

    public function update(StoreAndUpdateStoreRequest $request, Store $store): JsonResponse
    {
        $store->update($request->validated());

        $store->load(['region', 'warehouse']);

        return response()->json([
            'success' => true,
            'message' => 'Store updated successful.',
            'data' => new StoreDetailResource($store),
        ]);
    }

    public function destroy(Store $store): JsonResponse
    {
        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Store deleted successful.',
        ]);
    }

    public function dropdown(): JsonResponse
    {
        $user = Auth::user();
        $warehouse_id = $user->warehouse_id ?? null;

        $query = Store::with('warehouse:id,name')
            ->select('id', 'store_code', 'name', 'warehouse_id', 'street', 'phone_number')->where('status', true);

        if ($warehouse_id) {
            $query->where('warehouse_id', $warehouse_id);
        }

        $store = $query->get();

        return response()->json([
            'success' => true,
            'data' => $store
        ]);
    }

    public function filter(): JsonResponse
    {
        $user = Auth::user();
        $warehouse_id = $user->warehouse_id ?? null;

        $query = Store::with('warehouse:id,name')
            ->select('id', 'store_code', 'name', 'warehouse_id', 'street', 'phone_number');

        if ($warehouse_id) {
            $query->where('warehouse_id', $warehouse_id);
        }

        $store = $query->get();

        return response()->json([
            'success' => true,
            'data' => $store
        ]);
    }
}
