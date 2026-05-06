<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmStockReturnRequest;
use App\Models\StockReturns;
use App\Repositories\ReturnRepository;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ReturnConfirmController extends Controller
{
    public function __construct(
        protected ReturnRepository $repository
    ) {}

    public function updateStatus(ConfirmStockReturnRequest $request, StockReturns $stockReturn): JsonResponse
    {
        try {
            $userId = request()->user()->id;
            $status = ReturnStatus::from($request->validated('status'));

            $stockReturn = $this->repository->updateStatus(
                $stockReturn,
                $status,
                $request->validated('approved_quantity')
                    ? (int) $request->validated('approved_quantity')
                    : null,
                $userId,
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
            'message' => 'Return status updated successfully.',
            'data' => $stockReturn,
        ]);
    }
}
