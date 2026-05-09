<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transfer\UpdateTransferStatusRequest;
use App\Http\Resources\TransferResource;
use App\Models\StockTransfers;
use App\Repositories\TransferRepository;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class TransferStatusController extends Controller
{
    public function __construct(
        protected TransferRepository $repository
    ) {}

    public function patch(UpdateTransferStatusRequest $request, StockTransfers $transfer): JsonResponse
    {
        try {
            $transfer = $this->repository->update($transfer, [
                'status' => $request->validated('status'),
                'confirmed_by' => $request->user()->id,
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transfer status updated successfully.',
            'data' => new TransferResource($transfer),
        ]);
    }

    public function allowedTransitions(StockTransfers $transfer): JsonResponse
    {
        $currentStatus = $transfer->status;
        $allowedStatuses = [];

        foreach ($currentStatus::cases() as $status) {
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

    private function getStatusLabel($status): string
    {
        return match ($status->value) {
            'draft' => 'Draft',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'received' => 'Received',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('-', ' ', $status->value)),
        };
    }
}
