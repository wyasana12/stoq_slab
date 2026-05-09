<?php

namespace App\Http\Controllers;

use App\Enums\DistributionStatus;
use App\Http\Requests\Distribution\StoreAndUpdateDistributionRequest;
use App\Http\Requests\Distribution\UpdateDistributionStatusRequest;
use App\Http\Resources\DistributionResource;
use App\Models\StockDistributions;
use App\Repositories\DistributionRepository;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

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
            'data' => DistributionResource::collection($distributions),
        ]);
    }

    public function store(StoreAndUpdateDistributionRequest $request): JsonResponse
    {
        try {
            $distribution = $this->repository->createDistribution($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Distribution created successfully.',
            'data' => new DistributionResource($distribution),
        ], 201);
    }

    public function show(StockDistributions $distribution): JsonResponse
    {
        $distribution->load('items.batch', 'warehouse', 'request', 'confirmedBy');

        return response()->json([
            'success' => true,
            'data' => new DistributionResource($distribution),
        ]);
    }

    public function update(
        StoreAndUpdateDistributionRequest $request,
        StockDistributions $distribution
    ): JsonResponse {
        try {
            $distribution = $this->repository->updateDistribution($distribution, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Distribution updated successfully.',
            'data' => new DistributionResource($distribution),
        ]);
    }

    public function updateStatus(
        UpdateDistributionStatusRequest $request,
        StockDistributions $distribution
    ): JsonResponse {
        try {
            $status = DistributionStatus::from($request->validated('status'));

            $distribution = $this->repository->updateStatus(
                $distribution,
                $status,
                $request->validated('confirmed_by'),
                $request->validated('notes')
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Distribution status updated successfully.',
            'data' => new DistributionResource($distribution),
        ]);
    }

    public function destroy(StockDistributions $distribution): JsonResponse
    {
        if (! $this->repository->deleteDistribution($distribution)) {
            return response()->json([
                'success' => false,
                'message' => 'Distribution cannot be deleted.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Distribution deleted successfully.',
        ]);
    }
}
