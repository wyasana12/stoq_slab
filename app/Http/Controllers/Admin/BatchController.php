<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Batch\BulkPrintRequest;
use App\Http\Resources\Batch\BatchDetailResource;
use App\Http\Resources\Batch\BatchListResource;
use App\Http\Resources\Batch\BatchPrintResource;
use App\Models\Batch;
use App\Services\BatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BatchController extends Controller
{
    protected BatchService $batchService;

    public function __construct(BatchService $batchService)
    {
        $this->batchService = $batchService;
    }

    public function index(): JsonResponse
    {
        try {
            $allBatches = $this->batchService->getAllBatches();

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

    public function show(Batch $batch): JsonResponse
    {
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

    public function preview(Batch $batch)
    {
        if (empty($batch->barcode)) {
            abort(404, 'Barcode not found.');
        }

        if (!Storage::disk('public')->exists($batch->barcode)) {
            abort(404, 'Barcode file not found.');
        }

        return response(
            Storage::disk('public')->get($batch->barcode),
            200,
            [
                'Content-Type' => 'image/svg+xml',
                'Cache-Control' => 'public, max-age=86400',
            ]
        );
    }

    public function print(Batch $batch)
    {
        if (!$batch->barcode) {
            abort(404, 'QR Code not found.');
        }

        $path = storage_path('app/public/' . $batch->barcode);

        return response()->download(
            $path,
            "{$batch->batch_code}.svg"
        );
    }

    public function printBySelected(BulkPrintRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $batches = $this->batchService->getSelectedForPrint($validated['batch_ids']);

            if ($batches->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No batches found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Selected batches ready for printing.',
                'data' => BatchPrintResource::collection($batches),
            ]);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to selected batches for printing.',
                'error' => $err->getMessage(),
            ]);
        }
    }

    public function updateStatus(Request $request, Batch $batch): JsonResponse
    {
        try {
            $validated = $request->validate([
                'condition' => 'nullable|string',
                'rack_location' => 'nullable|string',
            ]);

            $updateData = [];

            if (isset($validated['condition'])) {
                $updateData['condition'] = $validated['condition'];
            }

            if (array_key_exists('rack_location', $validated) && !empty($validated['rack_location'])) {
                $rackLocation = \App\Models\RackLocation::where('location_code', $validated['rack_location'])->first();
                if (!$rackLocation) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Lokasi rak tidak ditemukan.',
                    ], 404);
                }
                $updateData['rack_id'] = $rackLocation->id;
            }

            if (!empty($updateData)) {
                $batch->update($updateData);
                $batch = $batch->fresh();
            }

            $this->batchService->generateBarcode($batch);

            $batchDetail = $this->batchService->getBatchDetail($batch);

            return response()->json([
                'success' => true,
                'message' => 'Status batch berhasil diperbarui.',
                'data' => new BatchDetailResource($batchDetail),
            ], 200);
        } catch (\Exception $err) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status batch.',
                'error' => $err->getMessage(),
            ], 500);
        }
    }
}
