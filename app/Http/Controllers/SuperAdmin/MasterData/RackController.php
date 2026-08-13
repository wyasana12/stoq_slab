<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rack\StoreRackRequest;
use App\Http\Resources\Rack\RackWarehouseListResource;
use App\Models\RackLocation;
use App\Models\RackWarehouse;
use App\Services\RackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RackController extends Controller
{
    protected RackService $rackService;

    public function __construct(RackService $rackService)
    {
        $this->rackService = $rackService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 10);
            $search  = $request->query('search');
            $status  = $request->query('status');
            $categoryId = $request->query('category_id');

            $allRacks = $this->rackService->getAllRack($perPage, $search, $status, $categoryId);

            return response()->json([
                'success' => true,
                'data' => RackWarehouseListResource::collection($allRacks),
                'meta' => [
                    'current_page' => $allRacks->currentPage(),
                    'last_page'    => $allRacks->lastPage(),
                    'per_page'     => $allRacks->perPage(),
                    'total'        => $allRacks->total(),
                ],
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve all racks.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Get rack statistics for dashboard cards.
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->rackService->getStatistics();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve rack statistics.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRackRequest $request): JsonResponse
    {
        try {
            $rack = $this->rackService->create($request->validated());

            return response()->json([
                'success' => true,
                'messages' => 'Rack berhasil dibuat.',
                'data' => new RackWarehouseListResource($rack),
            ], 201);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Gagal membuat rack.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(RackWarehouse $rack): JsonResponse
    {
        try {
            $rackDetail = $this->rackService->getRackDetail($rack);

            return response()->json([
                'success' => true,
                'data' => new RackWarehouseListResource($rackDetail),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve rack detail.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RackWarehouse $rack): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:AVAILABLE,FULL,MAINTENANCE'
            ]);

            if ($validated['status'] === 'MAINTENANCE') {
                $usedBinsCount = RackLocation::where('rack_id', $rack->id)
                                             ->where('used', '>', 0)
                                             ->count();
                if ($usedBinsCount > 0) {
                    return response()->json([
                        'success' => false,
                        'messages' => 'Tidak dapat mengubah status ke Maintenance karena ada bin yang masih berisi barang.'
                    ], 422);
                }
            }

            $rack->update(['status' => $validated['status']]);

            // Cascade status to all bins if rack status is MAINTENANCE or AVAILABLE
            if ($validated['status'] === 'MAINTENANCE') {
                RackLocation::where('rack_id', $rack->id)->update(['status' => 'MAINTENANCE']);
            } elseif ($validated['status'] === 'AVAILABLE') {
                // Since maintenance is only allowed when empty, restoring to available 
                // means all bins are empty and should be available.
                RackLocation::where('rack_id', $rack->id)
                            ->where('status', 'MAINTENANCE')
                            ->update(['status' => 'AVAILABLE']);
            }

            return response()->json([
                'success' => true,
                'messages' => 'Status rack berhasil diperbarui.',
                'data' => new RackWarehouseListResource($rack->fresh()),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Gagal memperbarui status rack.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RackWarehouse $rack): JsonResponse
    {
        try {
            // Ensure rack is completely empty before deletion
            $usedBinsCount = RackLocation::where('rack_id', $rack->id)
                                         ->where('used', '>', 0)
                                         ->count();
            if ($usedBinsCount > 0) {
                return response()->json([
                    'success' => false,
                    'messages' => 'Tidak dapat menghapus rak karena masih ada bin yang berisi barang.'
                ], 422);
            }

            $this->rackService->deleteRack($rack);

            return response()->json([
                'success' => true,
                'messages' => 'Rack berhasil dihapus.',
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Gagal menghapus rack.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function dropdownRack(): JsonResponse
    {
        $warehouseId = Auth::user()?->warehouse_id;

        $racks = RackWarehouse::where('warehouse_id', $warehouseId)
            ->where('status', '!=', 'MAINTENANCE')
            ->select('id', 'rack_code')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $racks
        ]);
    }

    public function dropdownLevel(Request $request): JsonResponse
    {
        $request->validate(['rack_id' => 'required|exists:rack_warehouses,id']);

        $levels = RackLocation::where('rack_id', $request->rack_id)
            ->where('status', 'AVAILABLE')
            ->distinct()
            ->orderBy('level', 'asc')
            ->pluck('level')
            ->map(fn($l) => ['id' => $l, 'level' => "Level $l"]);

        return response()->json([
            'success' => true,
            'data' => $levels
        ]);
    }

    public function dropdownBin(Request $request): JsonResponse
    {
        $request->validate([
            'rack_id' => 'required|exists:rack_warehouses,id',
            'level'   => 'required|integer'
        ]);

        $bins = RackLocation::where('rack_id', $request->rack_id)
            ->where('level', $request->level)
            ->whereIn('status', ['AVAILABLE'])
            ->get(['id', 'bin', 'location_code']);

        return response()->json([
            'success' => true,
            'data' => $bins
        ]);
    }

    public function getAvailableLocationsForPreview()
    {
        $warehouseId = Auth::user()->warehouse_id;

        $locations = RackLocation::select(
            'rack_locations.id',
            'rack_warehouses.rack_code',
            'rack_warehouses.status as rack_status',
            'rack_locations.level',
            'rack_locations.bin',
            'rack_locations.capacity',
            'rack_locations.used',
            'rack_locations.batch_id',
            'rack_locations.status as location_status'
        )
            ->join('rack_warehouses', 'rack_warehouses.id', '=', 'rack_locations.rack_id')
            ->where('rack_warehouses.warehouse_id', $warehouseId)
            ->orderBy('rack_warehouses.rack_code', 'asc')
            ->orderBy('rack_locations.level', 'asc')
            ->orderBy('rack_locations.bin', 'asc')
            ->get()
            ->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'rack_code' => $loc->rack_code,
                    'rack_status' => $loc->rack_status,
                    'level' => $loc->level,
                    'bin' => $loc->bin,
                    'location_status' => $loc->location_status,
                    'batch_id' => $loc->batch_id,
                    'available_capacity' => max(0, (int)$loc->capacity - (int)$loc->used)
                ];
            });

        return response()->json(['data' => $locations]);
    }
    public function updateLocationStatus(Request $request, RackLocation $location)
    {
        $request->validate([
            'status' => 'required|in:AVAILABLE,MAINTENANCE'
        ]);

        if ($request->status === 'AVAILABLE' && $location->rack && $location->rack->status === 'MAINTENANCE') {
            return response()->json([
                'success' => false,
                'messages' => 'Tidak dapat mengubah bin ke Available karena Rak Utama sedang dalam perbaikan (Maintenance).',
            ], 422);
        }

        if ($request->status === 'MAINTENANCE' && $location->used > 0) {
            return response()->json([
                'success' => false,
                'messages' => 'Tidak dapat mengubah status ke Maintenance karena bin ini masih berisi barang.',
            ], 422);
        }

        $location->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'messages' => 'Status bin berhasil diperbarui.'
        ]);
    }
}
