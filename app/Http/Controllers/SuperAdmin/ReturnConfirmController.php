<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Return\ConfirmStockReturnRequest;
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
}
