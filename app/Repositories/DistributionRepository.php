<?php

namespace App\Repositories;

use App\Enums\DistributionStatus;
use App\Enums\RoleName;
use App\Models\Batch;
use App\Models\Store;
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

            // If a store_id is provided, pull outlet data from the Store master table
            if (! empty($data['store_id'])) {
                $store = Store::find($data['store_id']);
                if ($store) {
                    $data['outlet_name'] = $store->name;
                    $data['outlet_address'] = $store->address;
                    $data['outlet_phone'] = $store->phone;
                    $data['outlet_contact'] = $store->phone; // keep contact consistent
                }
            }

            $distribution = StockDistributions::create([
                'distribution_code' => 'DIST-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                'warehouse_id'      => $data['warehouse_id'],
                'store_id'          => $data['store_id'] ?? null,  // ← tambah ini

                'outlet_name'       => $data['outlet_name'] ?? null,
                'outlet_address'    => $data['outlet_address'] ?? null,
                'outlet_phone'      => $data['outlet_phone'] ?? $data['outlet_contact'] ?? null,
                'outlet_contact'    => $data['outlet_contact'] ?? $data['outlet_phone'] ?? null,
                'dispatched_at'     => null,
                'requested_by'      => $requestedBy,
                'confirmed_by'      => $data['confirmed_by'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'status'            => $status,
            ]);
            foreach ($data['items'] as $item) {
                StockDistributionItem::create([
                    'distribution_id' => $distribution->id,
                    'batch_id' => $item['batch_id'],
                    'requested_quantity' => $item['requested_quantity'],
                    'approved_quantity' => $item['approved_quantity'] ?? 0,
                ]);
            }

            return $distribution->load('items.batch', 'warehouse', 'request', 'confirmedBy');
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

            $currentStatus = DistributionStatus::tryFrom($distribution->status);

            if (isset($data['status']) && $data['status'] !== $distribution->status) {
                $newStatus = DistributionStatus::from($data['status']);

                if (! $currentStatus->canTransition($newStatus)) {
                    throw new InvalidArgumentException(
                        "Transisi status {$distribution->status} ke {$data['status']} tidak diizinkan."
                    );
                }

                $distribution->status = $newStatus->value;
            }


            $distribution->update([
                'warehouse_id' => $data['warehouse_id'] ?? $distribution->warehouse_id,
                'requested_by' => $data['requested_by'] ?? $distribution->requested_by,
                'confirmed_by' => $data['confirmed_by'] ?? $distribution->confirmed_by,
                'notes' => $data['notes'] ?? $distribution->notes,
                'status' => $distribution->status,
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

            return $distribution->refresh()->load('items.batch', 'warehouse', 'request', 'confirmedBy');
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
                    $distributionItem = $distributionItems[$item['id']] ?? null;
                    if (! $distributionItem) {
                        throw new InvalidArgumentException("Item distribusi tidak valid: {$item['id']}.");
                    }
                    $approvedQuantity = (int) $item['approved_quantity'];
                    $batch = $distributionItem->batch;

                    if (! $batch) {
                        throw new InvalidArgumentException('Batch tidak ditemukan.');
                    }

                    $distributionItem = $distributionItems[$item['id']] ?? null;
                    if (! $distributionItem) {
                        throw new InvalidArgumentException("Item distribusi tidak valid: {$item['id']}.");
                    }

                    $approvedQuantity = (int) $item['approved_quantity'];
                    if ($approvedQuantity > $batch->current_quantity) {
                        throw new InvalidArgumentException(
                            "Stok batch {$batch->batch_code} tidak cukup. " .
                                "Tersedia: {$batch->current_quantity}, diminta: {$approvedQuantity}."
                        );
                    }
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

            return $distribution->refresh()->load('items.batch', 'warehouse', 'request', 'confirmedBy');
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
                'Distribusi selesai ke ' . ($distribution->store?->name ?? 'toko')
            );
        }
    }
}
