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
        protected TransferRepository $repository,
        protected \App\Services\TransferNotificationService $notificationService
    ) {}

    /**
     * Update the status of a transfer
     *
     * @param UpdateTransferStatusRequest $request
     * @param StockTransfers $transfer
     * @return JsonResponse
     */
    public function patch(UpdateTransferStatusRequest $request, StockTransfers $transfer): JsonResponse
    {
        $userId = request()->user()->id ?? null;
        $newStatus = $request->getStatus();
        $currentStatus = $transfer->status;

        // Validate transition is allowed
        if (!$currentStatus->canTransition($newStatus)) {
            return response()->json([
                'success' => false,
                'message' => "Cannot transition from {$currentStatus->value} to {$newStatus->value}. Transition not allowed.",
            ], 422);
        }

        $data = [
            'status' => $newStatus->value,
            'confirmed_by' => $userId,
            'batch' => $request->getBatch(),
        ];

        // Update the status
        $transfer = $this->repository->update($transfer, $data);

        $this->notificationService->sendTransferNotification($transfer, $newStatus);

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
