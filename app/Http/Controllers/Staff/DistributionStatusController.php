<?php

namespace App\Http\Controllers\Staff;

use App\Enums\DistributionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Distribution\ConfirmDistributionRequest;
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

    public function confirm(ConfirmDistributionRequest $request, StockDistributions $distribution): JsonResponse
    {
        try {
            $status = DistributionStatus::from($request->validated('status'));

            $distribution = $this->repository->updateStatus(
                $distribution,
                $status,
                $request->validated('confirmed_by'),
                $request->validated('notes'),
                $request->validated('items')
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Distribusi berhasil dikonfirmasi.',
            'data' => new DistributionResource($distribution),
        ]);
    }

    public function allowedTransitions(StockDistributions $distribution): JsonResponse
    {
        $currentStatus = DistributionStatus::from($distribution->status);
        $allowedStatuses = [];

        foreach (DistributionStatus::cases() as $status) {
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

    private function getStatusLabel(DistributionStatus $status): string
    {
        return match ($status) {
            DistributionStatus::DRAFT => 'Draft',
            DistributionStatus::WAITING_APPROVAL => 'Waiting Approval',
            DistributionStatus::APPROVED => 'Approved',
            DistributionStatus::REJECTED => 'Rejected',
            DistributionStatus::PREPARING => 'Preparing',
            DistributionStatus::SHIPPED => 'Shipped',
            DistributionStatus::DELIVERED => 'Delivered',
            DistributionStatus::COMPLETED => 'Completed',
            DistributionStatus::CANCELED => 'Canceled',
        };
    }
}
