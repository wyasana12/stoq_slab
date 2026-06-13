<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PurchaseOrder\CreateAndUpdateRequestPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdateStatusPurchaseOrderRequest;
use App\Http\Resources\PurchaseOrder\PurchaseOrderDetailResource;
use App\Http\Resources\PurchaseOrder\PurchaseOrderListResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PurchaseOrderController extends Controller
{
    protected PurchaseOrderService $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    /**
     * Display a listing of purchase orders.
     *
     * @queryParam page int Example: 1
     * @queryParam per_page int Example: 10
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $allPurchaseOrders = $this->purchaseOrderService->getAllPurchaseOrders($perPage, $userId);

            return response()->json([
                'success' => true,
                'data' => PurchaseOrderListResource::collection($allPurchaseOrders)->response()->getData(true),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve all purchase orders.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function request(CreateAndUpdateRequestPurchaseOrderRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $requestPurchaseOrder = $this->purchaseOrderService->createRequestPurchaseOrder($request->validated(), $userId);

            return response()->json([
                'success' => true,
                'messages' => 'Purchase order request successful.',
                'data' => new PurchaseOrderDetailResource($requestPurchaseOrder),
            ], 200);
        } catch (\InvalidArgumentException $err) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'items' => [$err->getMessage()]
                ],
            ], 422);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to request purchase order.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function update(CreateAndUpdateRequestPurchaseOrderRequest $request, PurchaseOrder $purchase): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $updateRequest = $this->purchaseOrderService->updateRequestPurchaseOrder(
                $purchase,
                $request->validated(),
                $userId,
            );

            return response()->json([
                'success' => true,
                'messages' => 'Purchase order update request successful.',
                'data' => new PurchaseOrderDetailResource($updateRequest),
            ], 200);
        } catch (\InvalidArgumentException $err) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'items' => [$err->getMessage()]
                ],
            ], 422);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to update request purchase order.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function confirmation(Request $request): JsonResponse
    {
        try {
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $allConfirmations = $this->purchaseOrderService->getAllConfirmations($perPage);

            return response()->json([
                'success' => true,
                'data' => PurchaseOrderListResource::collection($allConfirmations)->response()->getData(true),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve all purchase orders.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function status(UpdateStatusPurchaseOrderRequest $request, PurchaseOrder $purchase): JsonResponse
    {
        try {
            $updateStatus = $this->purchaseOrderService->updateStatusPurchaseOrder(
                $purchase,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'messages' => 'Purchase order update status successful.',
                'data' => new PurchaseOrderDetailResource($updateStatus),
            ], 200);
        } catch (\InvalidArgumentException $err) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'items' => [$err->getMessage()]
                ],
            ], 422);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to update status purchase order.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, PurchaseOrder $purchase): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $purchaseDetail = $this->purchaseOrderService->getPurchaseOrderDetail($purchase, $userId);

            return response()->json([
                'success' => true,
                'data' => new PurchaseOrderDetailResource($purchaseDetail),
            ], 200);
        } catch (AuthorizationException $err) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'items' => [$err->getMessage()]
                ],
            ], 403);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve purchase order details.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, PurchaseOrder $purchase): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $this->purchaseOrderService->softDeletePurchaseOrder($purchase, $userId);

            return response()->json([
                'success' => true,
                'messages' => 'Purchase order deleted successful.'
            ], 200);
        } catch (AuthorizationException $err) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'items' => [$err->getMessage()]
                ],
            ], 403);
        } catch (InvalidArgumentException $err) {
            return response()->json([
                'success' => false,
                'errors' => [
                    'items' => [$err->getMessage()]
                ],
            ], 422);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to delete purchase order.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function trashed(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $perPage = $request->query('per_page', 10);
            $page = $request->query('page', 1);

            $trashedPurchases = $this->purchaseOrderService->getTrashedPurchaseOrder($perPage, $userId);

            return response()->json([
                'success' => true,
                'data' => PurchaseOrderListResource::collection($trashedPurchases)->response()->getData(true),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retreive trashed purchase orders.',
                'error' => $err->getMessage()
            ], 500);
        }
    }

    public function restore(PurchaseOrder $purchase, Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $restoredPurchase = $this->purchaseOrderService->restorePurchaseOrder($purchase, $userId);

            return response()->json([
                'success' => true,
                'messages' => 'Purchase order restored successful.',
                'data' => new PurchaseOrderDetailResource($restoredPurchase)
            ], 200);
        } catch (AuthorizationException $err) {
            return response()->json([
                'success' => false,
                'messages' => $err->getMessage()
            ], 403);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to restored purchase order.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function forceDestroy(PurchaseOrder $purchase, Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $this->purchaseOrderService->forceDeletePurchaseOrder($purchase, $userId);

            return response()->json([
                'success' => true,
                'messages' => 'Purchase order force deleted successful.',
            ], 200);
        } catch (AuthorizationException $err) {
            return response()->json([
                'success' => false,
                'messages' => $err->getMessage(),
            ], 403);
        } catch (\Exception $err) {
            \Log::error('PO Create Error: ' . $err->getMessage() . ' Trace: ' . $err->getTraceAsString());
            return response()->json([
                'success' => false,
                'messages' => 'Failed to force deleted purchase order.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }

    public function dropdown(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $purchases = PurchaseOrder::select('id', 'po_code')->where('status', 'ordered')->where('warehouse_id', $user->warehouse_id)->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $purchases
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'messages' => 'Failed to retrieve purchase order dropdown',
                'error' => $err->getMessage(),
            ]);
        }
    }
}
