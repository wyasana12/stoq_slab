<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Return\ConfirmStockReturnRequest;
use App\Enums\ReturnStatus;
use App\Models\StockReturns;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class ReturnConfirmController extends Controller
{
    protected ReturnService $returnService;

    public function __construct(ReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    public function updateStatus(ConfirmStockReturnRequest $request, StockReturns $stockReturn): JsonResponse
    {
        try {
            $stockReturn = $this->returnService->confirmReturn(
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
            'message' => 'Return status updated successfully.',
            'data' => $stockReturn,
        ]);
    }

    public function allowedTransitions(StockReturns $stockReturn): JsonResponse
    {
        $currentStatus = ReturnStatus::from($stockReturn->status);
        $allowedStatuses = [];

        foreach (ReturnStatus::cases() as $status) {
            if ($currentStatus->canTransition($status)) {
                $allowedStatuses[] = [
                    'status' => $status->value,
                    'label' => $this->getStatusLabel($status),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'current_status' => $currentStatus->value,
            'current_status_label' => $this->getStatusLabel($currentStatus),
            'allowed_transitions' => $allowedStatuses,
        ]);
    }

    private function getStatusLabel(ReturnStatus $status): string
    {
        return match ($status) {
            ReturnStatus::REQUESTED => 'Requested',
            ReturnStatus::APPROVED => 'Approved',
            ReturnStatus::REJECTED => 'Rejected',
            ReturnStatus::RETURNING => 'Returning',
            ReturnStatus::COMPLETED => 'Completed',
            ReturnStatus::CANCELLED => 'Cancelled',
        };
    }
}
