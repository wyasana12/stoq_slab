<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransferResource;
use App\Models\StockTransfers;
use App\Repositories\TransferRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransferController extends Controller
{
    public function __construct(
        protected TransferRepository $repository,
        protected \App\Services\TransferNotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $transferType = $request->query('transfer_type');

        $transfers = $this->repository->getAll($transferType);

        return response()->json([
            'success' => true,
            'data' => \App\Http\Resources\TransferResource::collection($transfers),
        ]);
    }

    public function store(\App\Http\Requests\Transfer\StoreTransferRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $transfer = $this->repository->create($data);
            $this->notificationService->sendTransferNotification($transfer, $transfer->status);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transfer created successfully.',
            'data' => new \App\Http\Resources\TransferResource($transfer),
        ], 201);
    }

    public function show(StockTransfers $transfer): JsonResponse
    {
        $data = $this->repository->show($transfer);

        return response()->json([
            'success' => true,
            'data' => [
                'transfer' => new \App\Http\Resources\TransferResource($data['transfer']),
                'items' => $data['items'],
            ],
        ]);
    }

    public function update(\App\Http\Requests\transfer\UpdateTransferRequest $request, StockTransfers $transfer): JsonResponse
    {
        try {
            $transfer = $this->repository->update($transfer, $request->validated());
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Transfer updated successfully.',
            'data' => new \App\Http\Resources\TransferResource($transfer),
        ]);
    }

    public function destroy(StockTransfers $transfer): JsonResponse
    {
        $this->repository->delete($transfer);

        return response()->json([
            'success' => true,
            'message' => 'Transfer deleted successfully.',
        ]);
    }
}
