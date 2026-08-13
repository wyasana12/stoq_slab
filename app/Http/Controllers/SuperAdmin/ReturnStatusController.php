<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Return\ConfirmStockReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\StockReturns;
use App\Services\ReturnService;
use App\Enums\ReturnStatus;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ReturnStatusController extends Controller
{
    protected ReturnService $returnService;

    public function __construct(ReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    public function confirm(ConfirmStockReturnRequest $request, StockReturns $stockReturn): JsonResponse
    {
        try {
            $updatedReturn = $this->returnService->confirmReturn(
                $stockReturn,
                $request->validated(),
                $request->user()->id
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status return berhasil diperbarui.',
            'data' => new ReturnResource($updatedReturn),
        ]);
    }

    public function allowedTransitions(StockReturns $stockReturn): JsonResponse
    {
        $currentStatus = ReturnStatus::from($stockReturn->status);

        $allowed = collect(ReturnStatus::cases())
            ->filter(fn(ReturnStatus $status) => $currentStatus->canTransition($status))
            ->map(fn(ReturnStatus $status) => $status->value)
            ->values();

        return response()->json([
            'success' => true,
            'current_status' => $stockReturn->status,
            'allowed_transitions' => $allowed,
        ]);
    }
}
