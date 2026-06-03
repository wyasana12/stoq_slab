<?php

namespace App\Http\Controllers\Staff;

use App\Enums\MutationStatus;
use App\Enums\ReturnStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ReturnResource;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Repositories\ReturnRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExpiredConditionController extends Controller
{
    public function __construct(
        protected ReturnRepository $returnRepository
    ) {}

    public function process(Request $request, Batch $batch): JsonResponse
    {
        try {
            $payload = $request->validate([
                'action' => ['required', 'in:return,destroy'],
                'notes' => ['nullable', 'string', 'max:1000'],
            ]);

            $requestedQuantity = (int) $batch->current_quantity;

            if ($requestedQuantity < 1) {
                throw new InvalidArgumentException('Stok batch saat ini kosong.');
            }

            $daysUntilExpiry = $this->daysUntilExpiry($batch);

            if ($daysUntilExpiry === null || $daysUntilExpiry > 7) {
                throw new InvalidArgumentException('Aksi expired hanya tersedia untuk batch kritis atau expired.');
            }

            if ($request->user()?->warehouse_id && $request->user()->warehouse_id !== $batch->warehouse_id) {
                throw new InvalidArgumentException('Batch tidak berada di gudang user saat ini.');
            }

            if ($payload['action'] === 'return') {
                $stockReturn = $this->createReturnRequest($batch, $payload, $request->user()->id, $requestedQuantity);

                return response()->json([
                    'success' => true,
                    'message' => 'Permintaan return expired berhasil dibuat.',
                    'data' => new ReturnResource($stockReturn->load(['warehouse', 'product', 'request', 'confirm'])),
                ], 201);
            }

            $result = DB::transaction(function () use ($batch, $payload) {
                $lockedBatch = Batch::query()
                    ->whereKey($batch->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $requestedQuantity = (int) $lockedBatch->current_quantity;

                if ($requestedQuantity < 1) {
                    throw new InvalidArgumentException('Stok batch saat ini kosong.');
                }

                $beforeQuantity = (int) $lockedBatch->current_quantity;
                $decrement = $requestedQuantity;

                $lockedBatch->decrement('current_quantity', $decrement);
                $lockedBatch->refresh();

                $mutation = StockMutations::record(
                    $lockedBatch->warehouse_id,
                    $lockedBatch->id,
                    $beforeQuantity,
                    -$decrement,
                    MutationStatus::from('EXPIRED_DISPOSAL'),
                    'EXPIRED_DISPOSAL',
                    $lockedBatch->id,
                    $payload['notes'] ?? 'Pemusnahan batch expired ' . $lockedBatch->batch_code
                );

                return [
                    'batch' => $lockedBatch,
                    'mutation' => $mutation,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Pemusnahan batch expired berhasil diproses.',
                'data' => [
                    'batch_id' => $result['batch']->id,
                    'batch_code' => $result['batch']->batch_code,
                    'current_quantity' => $result['batch']->current_quantity,
                    'mutation_id' => $result['mutation']->id,
                    'mutation_status' => $result['mutation']->status,
                ],
            ]);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    private function createReturnRequest(Batch $batch, array $payload, string $userId, int $requestedQuantity)
    {
        return $this->returnRepository->createReturn([
            'batch_id' => $batch->id,
            'warehouse_id' => $batch->warehouse_id,
            'product_id' => $batch->product_id,
            'requested_quantity' => $requestedQuantity,
            'approved_quantity' => 0,
            'reason' => 'expired',
            'requested_by' => $userId,
            'notes' => $payload['notes'] ?? null,
            'status' => ReturnStatus::REQUESTED->value,
        ]);
    }

    private function daysUntilExpiry(Batch $batch): ?int
    {
        if (! $batch->expired_date) {
            return null;
        }

        return Carbon::now()->startOfDay()->diffInDays(Carbon::parse($batch->expired_date)->startOfDay(), false);
    }
}
