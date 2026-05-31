<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restock\UpdateRestockStatusRequest;
use App\Http\Resources\RestockResource;
use App\Models\Restock;
use App\Repositories\RestockRepository;
use Illuminate\Http\JsonResponse;

class RestockStatusController extends Controller
{
    public function __construct(protected RestockRepository $repository) {}
    /**
     * Update the status of a restock
     *
     * @param UpdateRestockStatusRequest $request
     * @param Restock $restock
     * @return JsonResponse
     */
    public function patch(UpdateRestockStatusRequest $request, Restock $restock): JsonResponse
    {
        $userId = request()->user()->id ?? null;
        $newStatus = $request->getStatus();
        $currentStatus = $restock->status;

        // Validate transition is allowed
        if (!$currentStatus->canTransition($newStatus)) {
            return response()->json([
                'success' => false,
                'message' => "Cannot transition from {$currentStatus->value} to {$newStatus->value}. Transition not allowed.",
                'current_status' => $currentStatus->value,
                'attempted_status' => $newStatus->value,
            ], 422);
        }

        // Update the status and record mutation if restocked
        $restock = $this->repository->update($restock, [
            'status' => $newStatus->value,
            'confirmed_by' => $userId,
            'products' => $request->getProducts(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Restock status updated from {$currentStatus->value} to {$newStatus->value}",
            'data' => new RestockResource($restock),
        ]);
    }

    /**
     * Get allowed transitions for a restock
     *
     * @param Restock $restock
     * @return JsonResponse
     */
    public function allowedTransitions(Restock $restock): JsonResponse
    {
        $currentStatus = $restock->status;
        $allowedStatuses = [];

        foreach ($restock->status::cases() as $status) {
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

    /**
     * Get a human-readable label for a status
     *
     * @param mixed $status
     * @return string
     */
    private function getStatusLabel($status): string
    {
        return match ($status->value) {
            'requested' => 'Requested',
            'approved' => 'Approved',
            'in-progress' => 'In Progress',
            'restocked' => 'Restocked',
            'failed' => 'Failed',
            'cancelled' => 'Cancelled',
            default => ucfirst(str_replace('-', ' ', $status->value)),
        };
    }
}
