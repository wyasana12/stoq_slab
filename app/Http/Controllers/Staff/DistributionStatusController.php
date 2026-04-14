<?php

namespace App\Http\Controllers\Staff;

use App\Enums\DistributionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDistributionStatusRequest;
use App\Http\Resources\DistributionResource;
use App\Models\StockDistributions;
use App\Repositories\DistributionRepository;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class DistributionStatusController extends Controller
{
    public function __construct(
        protected DistributionRepository $repository
    ) {}

    public function updateStatus(UpdateDistributionStatusRequest $request, StockDistributions $distribution): JsonResponse
    {
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
}
