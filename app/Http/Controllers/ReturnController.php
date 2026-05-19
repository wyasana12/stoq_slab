<?php

namespace App\Http\Controllers;

use App\Http\Requests\Return\StoreStockReturnRequest;
use App\Http\Requests\Return\UpdateStockReturnRequest;
use App\Http\Resources\ReturnResource;
use App\Models\StockReturns;
use App\Services\ReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ReturnController extends Controller
{
    protected ReturnService $returnService;

    public function __construct(ReturnService $returnService)
    {
        $this->returnService = $returnService;
    }

    public function index(Request $request): JsonResponse
    {
        $returns = StockReturns::query()
            ->with(['warehouse', 'receiving', 'product', 'request', 'confirm'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => ReturnResource::collection($returns),
        ]);
    }

    public function show(StockReturns $stockReturn): JsonResponse
    {
        $stockReturn->load(['warehouse', 'receiving', 'product', 'request', 'confirm']);

        return response()->json([
            'success' => true,
            'data' => new ReturnResource($stockReturn),
        ]);
    }

    public function store(StoreStockReturnRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            $stockReturn = $this->returnService->storeReturn($payload, $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Request return berhasil dibuat.',
            'data' => new ReturnResource($stockReturn),
        ], 201);
    }

    public function update(UpdateStockReturnRequest $request, StockReturns $stockReturn): JsonResponse
    {
        try {
            $stockReturn = $this->returnService->updateReturn($stockReturn, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Data return berhasil diupdate.',
            'data' => $stockReturn->fresh(),
        ]);
    }

    public function destroy(StockReturns $stockReturn): JsonResponse
    {
        try {
            $this->returnService->deleteReturn($stockReturn);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Data return berhasil dihapus.',
        ]);
    }
}
