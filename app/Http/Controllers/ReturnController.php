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

    public function scanBarcode(Request $request): JsonResponse
    {
        $barcode = $request->query('barcode');

        if (!$barcode) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode wajib diisi.',
            ], 400);
        }

        $batch = \App\Models\Batch::with(['receive', 'product', 'warehouse'])
            ->where('batch_code', $barcode)
            ->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode tidak ditemukan.',
            ], 404);
        }

        if (!$batch->receive) {
             return response()->json([
                'success' => false,
                'message' => 'Data penerimaan untuk produk ini tidak ditemukan.',
            ], 404);
        }

        $receivingDate = $batch->receive->receiving_date
            ? \Carbon\Carbon::parse($batch->receive->receiving_date)
            : $batch->receive->created_at;

        if (now()->diffInDays($receivingDate) > 3) {
            return response()->json([
                'success' => false,
                'message' => 'Batas waktu return sudah lewat.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'batch_id' => $batch->id,
                'receiving_id' => $batch->receiving_id,
                'product_id' => $batch->product_id,
                'product_name' => $batch->product->name ?? null,
                'current_quantity' => $batch->current_quantity,
                'receiving_date' => $receivingDate->toIso8601String(),
            ]
        ]);
    }

    public function store(StoreStockReturnRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            $payload['warehouse_id'] = $request->user()->warehouse_id;

            if (! $payload['warehouse_id']) {
                throw new InvalidArgumentException('User tidak memiliki gudang terkait.');
            }

            if ($request->hasFile('damage_proof')) {
                $payload = array_merge($payload, $this->saveProof($request->file('damage_proof')));
            }

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
            $payload = $request->validated();

            if ($request->hasFile('damage_proof')) {
                $payload = array_merge($payload, $this->saveProof($request->file('damage_proof')));
            }

            $stockReturn = $this->returnService->updateReturn($stockReturn, $payload);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Data return berhasil diupdate.',
            'data' => new ReturnResource($stockReturn->fresh()),
        ]);
    }

    private function saveProof($file): array
    {
        $path = $file->store('return_proofs', 'public');

        return [
            'damage_proof_path' => $path,
            'damage_proof_name' => $file->getClientOriginalName(),
            'damage_proof_mime' => $file->getClientMimeType(),
            'damage_proof_size' => $file->getSize(),
            'damage_proof_uploaded_at' => now(),
        ];
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
