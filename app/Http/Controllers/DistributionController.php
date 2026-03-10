<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAndUpdateDistributionRequest;
use App\Http\Resources\DistributionResource;
use App\Models\StockDistributions;
use App\Repositories\DistributionRepository;
use Illuminate\Http\JsonResponse;

class DistributionController extends Controller
{
    public function __construct(
        protected DistributionRepository $repository
    ) {}

    public function index(): JsonResponse
    {
        $distributions = $this->repository->getAllDistributions();

        return response()->json([
            'success' => true,
            'data'    => DistributionResource::collection($distributions),
        ]);
    }

    public function store(StoreAndUpdateDistributionRequest $request): JsonResponse
    {
        $distribution = $this->repository->createDistribution($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Distribution created successfully.',
            'data'    => new DistributionResource($distribution),
        ], 201);
    }

    public function show(StockDistributions $distribution): JsonResponse
    {
        // Load relasi jika diperlukan
        $distribution->load('items.batch');

        return response()->json([
            'success' => true,
            'data' => new DistributionResource($distribution),
        ]);
    }

    public function update(StoreAndUpdateDistributionRequest $request, StockDistributions $distribution): JsonResponse
    {
        $distribution = $this->repository->updateDistribution($distribution, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Distribution updated successfully.',
            'data'    => new DistributionResource($distribution),
        ]);
    }

    public function destroy(StockDistributions $distribution): JsonResponse
    {
        if (! $this->repository->deleteDistribution($distribution)) {
            return response()->json([
                'success' => false,
                'message' => 'Distribution cannot be deleted.'
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Distribution deleted successfully.',
        ]);
    }
}
