<?php

namespace App\Http\Controllers\SuperAdmin\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rack\StoreRackRequest;
use App\Http\Requests\Rack\UpdateRackLocationRequest;
use App\Http\Resources\Rack\RackLocationListResource;
use App\Http\Resources\Rack\RackWarehouseListResource;
use App\Models\RackLocation;
use App\Models\RackWarehouse;
use App\Services\RackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            $userId = $request->user()->id;
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $allRacks = $this->rackService->getAllRack($perPage);

            return response()->json([
                'success' => true,
                'data' => RackWarehouseListResource::collection($allRacks),
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
     * Store a newly created resource in storage.
     */
    public function store(StoreRackRequest $request): JsonResponse
    {
        try {
            $rack = $this->rackService->create($request->validated());

            return response()->json([
                'success' => true,
                'messages' => 'Rack created successful.',
                'data' => new RackLocationListResource($rack),
            ], 201);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to created rack.',
                'error' => $err->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(RackWarehouse $rack): JsonResponse
    {
        try {
            $locationList = $this->rackService->getRackDetail($rack);

            return response()->json([
                'success' => true,
                'data' => new RackLocationListResource($locationList),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve warehouse detail',
                'error' => $err->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRackLocationRequest $request, RackLocation $rackLocation): JsonResponse
    {
        try {
            $location = $this->rackService->updateLocation($rackLocation, $request->validated());

            return response()->json([
                'success' => true,
                'messages' => 'Success to updated rack location.',
                'data' => new RackLocationListResource($location),
            ], 201);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to updated rack location.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RackLocation $rackLocation)
    {
        //
    }
}
