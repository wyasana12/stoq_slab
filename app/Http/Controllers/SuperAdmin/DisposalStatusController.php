<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Disposal\ConfirmStockDisposalRequest;
use App\Http\Resources\DisposalResource;
use App\Models\StockDisposal;
use App\Services\DisposalService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class DisposalStatusController extends Controller
{
    protected DisposalService $disposalService;

    public function __construct(DisposalService $disposalService)
    {
        $this->disposalService = $disposalService;
    }

    public function confirm(ConfirmStockDisposalRequest $request, StockDisposal $stockDisposal): JsonResponse
    {
        try {
            $updatedDisposal = $this->disposalService->confirmDisposal(
                $stockDisposal,
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
            'message' => 'Status pemusnahan berhasil diperbarui.',
            'data' => new DisposalResource($updatedDisposal),
        ]);
    }
}
