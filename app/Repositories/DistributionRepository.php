<?php

namespace App\Repositories;

use App\Enums\DistributionStatus;
use App\Enums\RoleName;
use App\Models\Batch;
use App\Models\StockDistributions;
use App\Models\StockDistributionItem;
use App\Models\StockMutations;
use App\Models\User;
use App\Notifications\DistributionCreatedNotification;
use App\Enums\MutationStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DistributionRepository
{
    public function getAllDistributions(): Collection
    {
        return StockDistributions::with(['items.batch', 'warehouse', 'request', 'confirmedBy'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function createDistribution(array $data): StockDistributions
    {
        return DB::transaction(function () use ($data) {
            $requestedBy = $data['requested_by'] ?? Auth::id();

            $status = DistributionStatus::DRAFT->value;

            if (! empty($data['submit_for_approval'])) {
                $status = DistributionStatus::WAITING_APPROVAL->value;
            } elseif (($data['status'] ?? null) === DistributionStatus::WAITING_APPROVAL->value) {
                $status = DistributionStatus::WAITING_APPROVAL->value;
            }

            $distribution = StockDistributions::create([
                'distribution_code' => 'DIST-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                'warehouse_id' => $data['warehouse_id'],
                'location' => $data['location'],
                'dispatched_at' => null,
                'requested_by' => $requestedBy,
                'confirmed_by' => $data['confirmed_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
            ]);

            foreach ($data['items'] as $item) {
                StockDistributionItem::create([
                    'distribution_id' => $distribution->id,
                    'batch_id' => $item['batch_id'],
                    'requested_quantity' => $item['requested_quantity'],
                    'approved_quantity' => $item['approved_quantity'] ?? 0,
                ]);
            }

            return $distribution->load('items.batch');
        });
    }

    public function updateDistribution(StockDistributions $distribution, array $data): StockDistributions
    {
        return DB::transaction(function () use ($distribution, $data) {
            if (! in_array($distribution->status, [
                DistributionStatus::DRAFT->value,
                DistributionStatus::WAITING_APPROVAL->value,
            ], true)) {
                throw new InvalidArgumentException('Distribusi hanya dapat diubah saat draft atau waiting approval.');
            }

            $distribution->update([
                'warehouse_id' => $data['warehouse_id'] ?? $distribution->warehouse_id,
                'location' => $data['location'] ?? $distribution->location,
                'requested_by' => $data['requested_by'] ?? $distribution->requested_by,
                'confirmed_by' => $data['confirmed_by'] ?? $distribution->confirmed_by,
                'notes' => $data['notes'] ?? $distribution->notes,
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                $distribution->items()->delete();

                foreach ($data['items'] as $item) {
                    StockDistributionItem::create([
                        'distribution_id' => $distribution->id,
                        'batch_id' => $item['batch_id'],
                        'requested_quantity' => $item['requested_quantity'],
                        'approved_quantity' => $item['approved_quantity'] ?? 0,
                    ]);
                }
            }

            return $distribution->refresh()->load('items.batch');
        });
    }

    public function updateStatus(
        StockDistributions $distribution,
        DistributionStatus $newStatus,
        ?string $confirmedBy = null,
        ?string $notes = null,
        ?array $items = null
    ): StockDistributions {
        return DB::transaction(function () use ($distribution, $newStatus, $confirmedBy, $notes, $items) {
            $currentStatus = DistributionStatus::tryFrom($distribution->status);

            if (! $currentStatus) {
                throw new InvalidArgumentException('Status distribusi saat ini tidak valid.');
            }

            if (! $currentStatus->canTransition($newStatus)) {
                throw new InvalidArgumentException(
                    "Transisi status {$currentStatus->value} ke {$newStatus->value} tidak diizinkan."
                );
            }

            if ($newStatus === DistributionStatus::APPROVED) {
                if (! is_array($items) || count($items) === 0) {
                    throw new InvalidArgumentException('Approved quantities harus diisi saat approve distribusi.');
                }

                $distributionItems = $distribution->items()->get()->keyBy('id');

                foreach ($items as $item) {
                    if (! isset($item['id']) || ! isset($item['approved_quantity'])) {
                        throw new InvalidArgumentException('Item approved quantity tidak valid.');
                    }

                    $distributionItem = $distributionItems[$item['id']] ?? null;
                    if (! $distributionItem) {
                        throw new InvalidArgumentException("Item distribusi tidak valid: {$item['id']}.");
                    }

                    $approvedQuantity = (int) $item['approved_quantity'];

                    if ($approvedQuantity > $distributionItem->requested_quantity) {
                        throw new InvalidArgumentException('Approved quantity tidak boleh lebih besar dari requested quantity.');
                    }

                    $distributionItem->update([
                        'approved_quantity' => $approvedQuantity,
                    ]);
                }
            }

            if ($confirmedBy) {
                $distribution->confirmed_by = $confirmedBy;
            }

            if ($notes !== null) {
                $distribution->notes = $notes;
            }

            if ($newStatus === DistributionStatus::SHIPPED) {
                $distribution->dispatched_at = now();
            }

            $distribution->status = $newStatus->value;
            $distribution->save();

            if ($newStatus === DistributionStatus::SHIPPED) {
                $this->applyStockMutation($distribution);
            }

            return $distribution->refresh()->load('items.batch');
        });
    }

    public function deleteDistribution(StockDistributions $distribution): bool
    {
        return DB::transaction(function () use ($distribution) {
            if (! in_array($distribution->status, [
                DistributionStatus::DRAFT->value,
                DistributionStatus::WAITING_APPROVAL->value,
                DistributionStatus::REJECTED->value,
                DistributionStatus::CANCELED->value,
            ], true)) {
                return false;
            }

            $distribution->items()->delete();

            return $distribution->delete();
        });
    }

    private function applyStockMutation(StockDistributions $distribution): void
    {
        $distribution->loadMissing('items.batch');

        foreach ($distribution->items as $item) {
            $batch = $item->batch;

            if (! $batch) {
                throw new ModelNotFoundException('Batch tidak ditemukan untuk item distribusi.');
            }

            $quantity = $item->approved_quantity > 0 ? $item->approved_quantity : $item->requested_quantity;

            if ($batch->current_quantity < $quantity) {
                throw new InvalidArgumentException('Stok batch tidak cukup untuk menyelesaikan distribusi.');
            }

            $before = $batch->current_quantity;
            $batch->decrement('current_quantity', $quantity);

            StockMutations::record(
                $batch->warehouse_id,
                $batch->id,
                $before,
                $quantity,
                MutationStatus::DISTRIBUTION_COMPLETED,
                'DISTRIBUTION',
                $distribution->id,
                'Distribusi selesai ke ' . $distribution->location
            );
        }
    }
}
