<?php

namespace App\Http\Controllers;

use App\Enums\ReturnStatus;
use App\Http\Requests\ConfirmStockReturnRequest;
use App\Http\Requests\StoreStockReturnRequest;
use App\Http\Requests\UpdateStockReturnRequest;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Models\StockReturns;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

use function Illuminate\Support\now;

class ReturnController extends Controller
{

public function index(Request $request): JsonResponse
    {
        $returns = StockReturns::query()
            ->with(['warehouse', 'batch', 'request', 'confirm'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $returns,
        ]);
    }

    public function show(StockReturns $stockReturn): JsonResponse
    {
        $stockReturn->load(['warehouse', 'batch', 'request', 'confirm']);

        return response()->json([
            'success' => true,
            'data' => $stockReturn,
        ]);
    }
    public function store(StoreStockReturnRequest $request): JsonResponse
    {
        $requestedBy = $request->input('requested_by');

        if (! $requestedBy) {
            return response()->json([
                'message' => 'requested_by wajib diisi karena login belum tersedia.',
            ], 422);
        }

        $batch = Batch::query()
            ->whereKey($request->validated('batch_id'))
            ->firstOrFail();

        $requestedQty = (int) $request->validated('requested_quantity');

        if ($requestedQty > (int) $batch->current_quantity) {
            return response()->json([
                'message' => 'Requested quantity melebihi stok saat ini.',
            ], 422);
        }

        $stockReturn = StockReturns::create([
            'return_code' => 'RT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
            'warehouse_id' => $batch->warehouse_id,
            'batch_id' => $batch->id,
            'requested_quantity' => $requestedQty,
            'approved_quantity' => 0,
            'reason' => $request->validated('reason'),
            'requested_by' => $requestedBy,
            'confirmed_by' => null,
            'notes' => $request->validated('notes'),
            'status' => ReturnStatus::REQUESTED->value,
        ]);

        return response()->json([
            'message' => 'Request return berhasil dibuat.',
            'data' => $stockReturn,
        ], 201);
    }    public function update(UpdateStockReturnRequest $request, StockReturns $stockReturn): JsonResponse
    {
        if ($stockReturn->status !== ReturnStatus::REQUESTED->value) {
            return response()->json([
                'message' => 'Return yang sudah dikonfirmasi tidak bisa diubah.',
            ], 422);
        }

        $validated = $request->validated();

        $batch = Batch::query()->findOrFail($stockReturn->batch_id);

        if ((int) $validated['requested_quantity'] > (int) $batch->current_quantity) {
            return response()->json([
                'message' => 'Requested quantity melebihi stok saat ini.',
            ], 422);
        }

        $stockReturn->update([
            'requested_quantity' => (int) $validated['requested_quantity'],
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? $stockReturn->notes,
        ]);

        return response()->json([
            'message' => 'Data return berhasil diupdate.',
            'data' => $stockReturn->fresh(),
        ]);
    }

    public function destroy(StockReturns $stockReturn): JsonResponse
    {
        if ($stockReturn->status !== ReturnStatus::REQUESTED->value) {
            return response()->json([
                'message' => 'Return yang sudah dikonfirmasi tidak bisa dihapus.',
            ], 422);
        }

        $stockReturn->delete();

        return response()->json([
            'message' => 'Data return berhasil dihapus.',
        ]);
    }


    public function confirm(ConfirmStockReturnRequest $request, StockReturns $stockReturn): JsonResponse
    {
        $confirmedBy = $request->input('confirmed_by');

        if (! $confirmedBy) {
            return response()->json([
                'message' => 'confirmed_by wajib diisi.',
            ], 422);
        }

        $currentStatus = ReturnStatus::from($stockReturn->status);
        $newStatus = ReturnStatus::from($request->validated('status'));

        if (! $currentStatus->canTransition($newStatus)) {
            return response()->json([
                'message' => 'Transisi status tidak valid.',
            ], 422);
        }

        DB::transaction(function () use ($request, $stockReturn, $newStatus, $confirmedBy) {
            if ($newStatus === ReturnStatus::APPROVED) {
                $approvedQty = (int) $request->validated('approved_quantity');

                $batch = Batch::query()
                    ->lockForUpdate()
                    ->findOrFail($stockReturn->batch_id);

                if ($approvedQty > (int) $batch->current_quantity) {
                    abort(422, 'Approved quantity melebihi stok saat ini.');
                }

                $before = (int) $batch->current_quantity;
                $batch->decrement('current_quantity', $approvedQty);
                $batch->refresh();

                $stockReturn->update([
                    'approved_quantity' => $approvedQty,
                    'confirmed_by' => $confirmedBy,
                    'status' => ReturnStatus::APPROVED->value,
                    'notes' => $request->validated('notes') ?? $stockReturn->notes,
                ]);

                StockMutations::create([
                    'warehouse_id' => $batch->warehouse_id,
                    'batch_id' => $batch->id,
                    'change_quantity' => $approvedQty,
                    'before_quantity' => $before,
                    'after_quantity' => (int) $batch->current_quantity,
                    'reference_type' => 'RETURN',
                    'reference_id' => $stockReturn->id,
                    'notes' => 'Approve return ' . $stockReturn->return_code,
                    'status' => 'SUCCESS',
                ]);
            } else {
                $stockReturn->update([
                    'approved_quantity' => 0,
                    'confirmed_by' => $confirmedBy,
                    'status' => ReturnStatus::REJECTED->value,
                    'notes' => $request->validated('notes') ?? $stockReturn->notes,
                ]);
            }
        });

        return response()->json([
            'message' => 'Status return berhasil diupdate.',
        ]);
    }
}
