<?php

namespace App\Services;

use App\Enums\MutationStatus;
use App\Models\Batch;
use App\Models\StockDisposal;
use App\Models\StockMutations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DisposalService
{
    public function storeDisposal(array $data, string $userId): StockDisposal
    {
        $batch = Batch::findOrFail($data['batch_id']);

        if (empty($data['warehouse_id'])) {
            throw new InvalidArgumentException('Warehouse tidak tersedia untuk user saat ini.');
        }

        if ($data['requested_quantity'] > $batch->current_quantity) {
            throw new InvalidArgumentException('Jumlah pemusnahan melebihi stok yang tersedia di batch ini.');
        }

        return DB::transaction(function () use ($data, $userId) {
            return StockDisposal::create([
                'disposal_code' => 'DS-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
                'batch_id' => $data['batch_id'],
                'product_id' => $data['product_id'],
                'warehouse_id' => $data['warehouse_id'],
                'requested_quantity' => $data['requested_quantity'],
                'approved_quantity' => 0,
                'reason' => $data['reason'],
                'requested_by' => $userId,
                'notes' => $data['notes'] ?? null,
                'status' => 'requested',
            ]);
        });
    }

    public function confirmDisposal(StockDisposal $disposal, array $data, string $userId): StockDisposal
    {
        if ($disposal->status !== 'requested') {
            throw new InvalidArgumentException("Pemusnahan sudah diproses dengan status {$disposal->status}.");
        }

        $newStatus = $data['status']; // 'approved' or 'rejected'

        if ($newStatus === 'approved' && empty($data['approved_quantity'])) {
            throw new InvalidArgumentException('approved_quantity harus diisi saat approve.');
        }

        return DB::transaction(function () use ($disposal, $data, $newStatus, $userId) {
            if ($newStatus === 'approved') {
                $approvedQuantity = (int) $data['approved_quantity'];
                
                $batch = Batch::query()
                    ->whereKey($disposal->batch_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($batch->current_quantity < $approvedQuantity) {
                    throw new InvalidArgumentException('Stok batch tidak mencukupi untuk pemusnahan ini.');
                }

                $before = $batch->current_quantity;
                $batch->decrement('current_quantity', $approvedQuantity);
                $batch->refresh();

                // Determine mutation status based on reason
                $mutationStatus = match($disposal->reason) {
                    'expired' => MutationStatus::EXPIRED_DISPOSAL,
                    'damaged' => MutationStatus::DAMAGED_DISPOSAL,
                    'production_defect' => MutationStatus::PRODUCTION_DEFECT_DISPOSAL,
                    default => MutationStatus::EXPIRED_DISPOSAL,
                };

                StockMutations::record(
                    $batch->warehouse_id,
                    $batch->id,
                    $before,
                    -$approvedQuantity,
                    $mutationStatus,
                    'DISPOSAL',
                    $disposal->id,
                    'Pemusnahan disetujui ' . $disposal->disposal_code
                );

                $disposal->update([
                    'status' => $newStatus,
                    'approved_quantity' => $approvedQuantity,
                    'confirmed_by' => $userId,
                    'notes' => $data['notes'] ?? $disposal->notes,
                ]);
            } else {
                $disposal->update([
                    'status' => $newStatus,
                    'approved_quantity' => 0,
                    'confirmed_by' => $userId,
                    'notes' => $data['notes'] ?? $disposal->notes,
                ]);
            }

            return $disposal->refresh();
        });
    }
}
