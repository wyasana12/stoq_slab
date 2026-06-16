<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receive\StoreandUpdateReceiveRequest;
use App\Http\Resources\Receive\ReceiveDetailResource;
use App\Http\Resources\Receive\ReceiveListResource;
use App\Models\ProductReceiving;
use App\Services\ProductReceivingService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProductReceivingController extends Controller
{
    protected ProductReceivingService $productReceivingService;

    public function __construct(ProductReceivingService $productReceivingService)
    {
        $this->productReceivingService = $productReceivingService;
    }

    public function index(): JsonResponse
    {
        try {
            $allReceives = $this->productReceivingService->getAllReceives();
            $summary = $this->productReceivingService->getStats();

            return response()->json([
                'success' => true,
                'summary' => $summary,
                'data' => ReceiveListResource::collection($allReceives),
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
        } catch (AuthorizationException $err) {
            return response()->json([
                'success' => false,
                'messages' => $err->getMessage(),
            ], 403);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve receive details.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function store(StoreandUpdateReceiveRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $createReceive = $this->productReceivingService->createReceive($request->validated(), $userId);

            return response()->json([
                'success' => true,
                'messages' => 'Receive created successfull.',
                'data' => new ReceiveDetailResource($createReceive),
            ], 201);
        } catch (InvalidArgumentException $err) {
            return response()->json([
                'success' => false,
                'error' => $err->getMessage(),
            ], 400);
        } catch (Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to create receive.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function destroy(ProductReceiving $receive): JsonResponse
    {
        try {
            $this->productReceivingService->softDeleteReceive($receive);

            return response()->json([
                'success' => true,
                'messages' => 'Product receive deleted successful.',
            ], 200);
        } catch (InvalidArgumentException $err) {
            return response()->json([
                'success' => false,
                'messages' => $err->getMessage()
            ], 422);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to delete product receive.',
                'error' => $err->getMessage(),
            ]);
        }
    }

    public function trashed(): JsonResponse
    {
        try {
            $trashedReceive = $this->productReceivingService->getTrashedReceive();

            return response()->json([
                'success' => true,
                'data' => ReceiveListResource::collection($trashedReceive)
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve trashed product receive.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function restore(ProductReceiving $receive): JsonResponse
    {
        try {
            $restoredReceive = $this->productReceivingService->restoreReceive($receive);

            return response()->json([
                'success' => true,
                'messages' => 'Product receive restored successful.',
                'data' => new ReceiveDetailResource($restoredReceive),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to restored product receive.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function forceDestroy(ProductReceiving $receive): JsonResponse
    {
        try {
            $this->productReceivingService->forceDeleteReceive($receive);

            return response()->json([
                'success' => true,
                'messages' => 'Product receive force deleted successful.',
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to force deleted product receive.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function dropdownSource(Request $request): JsonResponse
    {
        try {
            $type = $request->query('type');

            if (!$type) {
                return response()->json(['success' => false, 'message' => 'Type is required'], 400);
            }

            $data = $this->productReceivingService->getAvailableDocuments($type);

            return response()->json([
                'success' => true,
                'data' => $data,
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch source options',
                'error'   => $err->getMessage(),
            ], 500);
        }
    }
}
