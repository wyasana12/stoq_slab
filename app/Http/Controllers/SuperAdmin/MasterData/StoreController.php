<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAndUpdateStoreRequest;
use App\Http\Resources\Store\StoreDetailResource;
use App\Http\Resources\Store\StoreListResource;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 10);
        $page = $request->query('page', 1);
        $query = Store::with('warehouse:id,name')->select('id', 'name', 'contact_person', 'phone_number', 'email', 'status', 'store_code', 'warehouse_id');

        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('store_code', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('status', filter_var($request->status, FILTER_VALIDATE_BOOLEAN));
        }

        $stores = $query->paginate($perPage);

        $summary = [
            'total_stores'    => Store::count(),
            'active_stores'   => Store::where('status', true)->count(),
            'inactive_stores' => Store::where('status', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge(
                StoreListResource::collection($stores)->response()->getData(true),
                ['summary' => $summary]
                ),
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
