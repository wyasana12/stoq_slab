<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Batch\BatchDetailResource;
use App\Http\Resources\Batch\BatchListResource;
use App\Models\Batch;
use App\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    protected $batchService;

    public function __construct(BatchService $batchService)
    {
        $this->batchService = $batchService;
    }

    public function index(Request $request): JsonResponse {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $allBatches = $this->batchService->getAllBatches($perPage);

            return response()->json([
                'success' => true,
                'data' => BatchListResource::collection($allBatches)
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve all batches.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function show(Batch $batch): JsonResponse {
        try {
            $batchDetail = $this->batchService->getBatchDetail($batch);

            return response()->json([
                'success' => true,
                'data' => new BatchDetailResource($batchDetail),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve batch details.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function generate(Batch $batch): JsonResponse
    {
        try {
            $updateBatch = $this->batchService->generateBarcode($batch);

            return response()->json([
                'success' => true,
                'messages' => 'QR Code generated successful.',
                'data' => new BatchDetailResource($updateBatch),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to generated qr code.',
                'error' => $err->getMessage(),
            ], 500);
        }    
    }
}
