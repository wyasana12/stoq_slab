<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Receive\ReceiveDetailResource;
use App\Http\Resources\Receive\ReceiveListResource;
use App\Models\ProductReceiving;
use App\Services\ProductReceivingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductReceivingController extends Controller
{
    protected $productReceivingService;

    public function __construct(ProductReceivingService $productReceivingService)
    {
        $this->productReceivingService = $productReceivingService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $allReceives = $this->productReceivingService->getAllReceives($perPage);

            return response()->json([
                'success' => true,
                'data' => ReceiveListResource::collection($allReceives)->response()->getData(true),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve all receives.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function show(ProductReceiving $receive): JsonResponse
    {
        try {
            $receiveDetail = $this->productReceivingService->getReceiveDetail($receive);

            return response()->json([
                'success' => true,
                'data' => new ReceiveDetailResource($receiveDetail),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve receive details.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }
}
