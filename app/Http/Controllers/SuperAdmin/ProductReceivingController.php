<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Receive\StoreandUpdateReceiveRequest;
use App\Http\Requests\Receive\UpdateProductReceiveRequest;
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

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['search', 'status']);
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $allReceives = $this->productReceivingService->getAllReceives($perPage, $filters);

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
        } catch(AuthorizationException $err) {
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
        }    catch (Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to create receive.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function updateItemsAndStatus(UpdateProductReceiveRequest $request, ProductReceiving $receive): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $updateItemsAndStatus = $this->productReceivingService->updateItemsAndStatus($receive, $request->validated(), $userId);

            return response()->json([
                'success' => true,
                'messages' => 'Product receive update items and status succesful.',
                'data' => new ReceiveDetailResource($updateItemsAndStatus)
            ], 200);
        } catch (InvalidArgumentException $err) {
            return response()->json([
                'success' => false,
                'messages' => $err->getMessage(),
            ], 422);
        } catch (AuthorizationException $err) {
            return response()->json([
                'success' => false,
                'messages' => $err->getMessage(),
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update items and status product receive.',
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

    public function trashed(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $trashedReceive = $this->productReceivingService->getTrashedReceive($perPage);

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
}
