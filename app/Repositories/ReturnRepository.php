<?php

namespace App\Repositories;

use App\Enums\ReturnStatus;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Models\StockReturns;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ReturnRepository
{
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

                StockMutations::create([
                    'warehouse_id' => $batch->warehouse_id,
                    'batch_id' => $batch->id,
                    'change_quantity' => $approvedQuantity,
                    'before_quantity' => $before,
                    'after_quantity' => $batch->current_quantity,
                    'reference_type' => 'RETURN',
                    'reference_id' => $stockReturn->id,
                    'notes' => 'Approve return ' . $stockReturn->return_code,
                    'status' => 'SUCCESS',
                ]);
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
