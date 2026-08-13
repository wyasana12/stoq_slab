<?php

namespace App\Repositories;

use App\Enums\ReturnStatus;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Models\StockReturns;
use App\Enums\MutationStatus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReturnRepository
{
    public function createReturn(array $data): StockReturns
    {
        return DB::transaction(function () use ($data) {
            return StockReturns::create([
                'return_code' => $data['return_code'] ?? 'RT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
                'warehouse_id' => $data['warehouse_id'],
                'batch_id' => $data['batch_id'],
                'requested_quantity' => $data['requested_quantity'],
                'approved_quantity' => $data['approved_quantity'] ?? 0,
                'reason' => $data['reason'],
                'requested_by' => $data['requested_by'],
                'confirmed_by' => $data['confirmed_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $data['status'] ?? ReturnStatus::REQUESTED->value,
            ]);
        });
    }

    public function updateReturn(StockReturns $stockReturn, array $data): StockReturns
    {
        if ($stockReturn->status !== ReturnStatus::REQUESTED->value) {
            throw new InvalidArgumentException('Return yang sudah dikonfirmasi tidak bisa diubah.');
        }

        $stockReturn->update([
            'requested_quantity' => $data['requested_quantity'],
            'reason' => $data['reason'],
            'notes' => $data['notes'] ?? $stockReturn->notes,
        ]);

        return $stockReturn->refresh();
    }

    public function deleteReturn(StockReturns $stockReturn): void
    {
        if ($stockReturn->status !== ReturnStatus::REQUESTED->value) {
            throw new InvalidArgumentException('Return yang sudah dikonfirmasi tidak bisa dihapus.');
        }

        $stockReturn->delete();
    }

    public function updateStatus(
        StockReturns $stockReturn,
        ReturnStatus $newStatus,
        ?int $approvedQuantity,
        ?string $confirmedBy,
        ?string $notes = null
    ): StockReturns {
        return DB::transaction(function () use ($stockReturn, $newStatus, $approvedQuantity, $confirmedBy, $notes) {
            $currentStatus = ReturnStatus::tryFrom($stockReturn->status);

            if (! $currentStatus) {
                throw new InvalidArgumentException('Status return saat ini tidak valid.');
            }

            if (! $currentStatus->canTransition($newStatus)) {
                throw new InvalidArgumentException(
                    "Transisi status {$currentStatus->value} ke {$newStatus->value} tidak diizinkan."
                );
            }

            if ($newStatus === ReturnStatus::APPROVED) {
                if ($approvedQuantity === null) {
                    throw new InvalidArgumentException('approved_quantity harus diisi untuk approve.');
                }

                $batch = Batch::query()
                    ->lockForUpdate()
                    ->find($stockReturn->batch_id);

                if (! $batch) {
                    throw new ModelNotFoundException('Batch return tidak ditemukan.');
                }

                if ($approvedQuantity > $batch->current_quantity) {
                    throw new InvalidArgumentException('Approved quantity melebihi stok saat ini.');
                }

                $before = $batch->current_quantity;
                $batch->decrement('current_quantity', $approvedQuantity);
                $batch->refresh();

                $stockReturn->update([
                    'approved_quantity' => $approvedQuantity,
                    'confirmed_by' => $confirmedBy,
                    'status' => $newStatus->value,
                    'notes' => $notes ?? $stockReturn->notes,
                ]);

                StockMutations::record(
                    $batch->warehouse_id,
                    $batch->id,
                    $before,
                    $approvedQuantity,
                    MutationStatus::RETURN_COMPLETED,
                    'RETURN',
                    $stockReturn->id,
                    'Approve return ' . $stockReturn->return_code
                );
            } else {
                $stockReturn->update([
                    'approved_quantity' => 0,
                    'confirmed_by' => $confirmedBy,
                    'status' => $newStatus->value,
                    'notes' => $notes ?? $stockReturn->notes,
                ]);
            }

            return $stockReturn->refresh();
        });
    }
}
