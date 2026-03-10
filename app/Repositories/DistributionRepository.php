<?php

namespace App\Repositories;

use App\Enums\DistributionStatus;
use App\Models\Batch;
use App\Models\StockDistributions;
use App\Models\StockDistributionItem;
use App\Models\StockMutations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DistributionRepository
{
    public function getAllDistributions(): Collection
    {
        return StockDistributions::with('items.batch')->get();
    }

    public function createDistribution(array $data): StockDistributions
    {
        return DB::transaction(function () use ($data) {
            $distribution = StockDistributions::create([
                'distribution_code' => "DIST-" . now()->format('Ymd') . "-" . rand(1000, 9999),
                'warehouse_id'      => $data['warehouse_id'],
                'location'          => $data['location'],
                'dispatched_at'     => now(),
                'requested_by'      => $data['requested_by'] ?? null,
                'confirmed_by'      => $data['confirmed_by'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'status'            => DistributionStatus::COMPLETED->value,
            ]);

            foreach ($data['items'] as $item) {
                StockDistributionItem::create([
                    'distribution_id'     => $distribution->id,
                    'batch_id'            => $item['batch_id'],
                    'requested_quantity'  => $item['requested_quantity'],
                    'approved_quantity'   => $item['requested_quantity'],
                ]);

                $batch = Batch::findOrFail($item['batch_id']);
                $before = $batch->current_quantity;
                $batch->decrement('current_quantity', $item['requested_quantity']);

                StockMutations::create([
                    'warehouse_id'     => $batch->warehouse_id,
                    'batch_id'         => $batch->id,
                    'change_quantity'  => $item['requested_quantity'],
                    'before_quantity'  => $before,
                    'after_quantity'   => $batch->current_quantity,
                    'reference_type'   => 'DISTRIBUTION',
                    'reference_id'     => $distribution->id,
                    'notes'            => 'Barang masuk ke ' . $distribution->location,
                ]);
            }

            return $distribution;
        });
    }

    public function updateDistribution(StockDistributions $distribution, array $data): StockDistributions
    {
        return DB::transaction(function () use ($distribution, $data) {
            // Update field dasar distribusi
            $distribution->update([
                'warehouse_id' => $data['warehouse_id'] ?? $distribution->warehouse_id,
                'location'     => $data['location'] ?? $distribution->location,
                'requested_by' => $data['requested_by'] ?? $distribution->requested_by,
                'confirmed_by' => $data['confirmed_by'] ?? $distribution->confirmed_by,
                'notes'        => $data['notes'] ?? $distribution->notes,
            ]);

            // Jika ada items yang diupdate
            if (isset($data['items']) && is_array($data['items'])) {
                // Hapus item lama dan revert mutasi stok
                foreach ($distribution->items as $oldItem) {
                    $batch = $oldItem->batch;
                    $before = $batch->current_quantity;

                    // Kembalikan stok ke state sebelum distribusi
                    $batch->increment('current_quantity', $oldItem->approved_quantity);

                    // Catat revert mutasi
                    StockMutations::create([
                        'warehouse_id'   => $batch->warehouse_id,
                        'batch_id'       => $batch->id,
                        'change_quantity' => -$oldItem->approved_quantity,
                        'before_quantity' => $before,
                        'after_quantity'  => $batch->current_quantity,
                        'reference_type' => 'DISTRIBUTION_REVERT',
                        'reference_id'   => $distribution->id,
                        'notes'          => 'Batal distribusi ke ' . $distribution->location,
                    ]);

                    $oldItem->delete();
                }

                // Buat item baru
                foreach ($data['items'] as $item) {
                    StockDistributionItem::create([
                        'distribution_id'     => $distribution->id,
                        'batch_id'            => $item['batch_id'],
                        'requested_quantity'  => $item['requested_quantity'],
                        'approved_quantity'   => $item['requested_quantity'],
                    ]);

                    $batch = Batch::findOrFail($item['batch_id']);
                    $before = $batch->current_quantity;
                    $batch->decrement('current_quantity', $item['requested_quantity']);

                    StockMutations::create([
                        'warehouse_id'     => $batch->warehouse_id,
                        'batch_id'         => $batch->id,
                        'change_quantity'  => $item['requested_quantity'],
                        'before_quantity'  => $before,
                        'after_quantity'   => $batch->current_quantity,
                        'reference_type'   => 'DISTRIBUTION',
                        'reference_id'     => $distribution->id,
                        'notes'            => 'Barang masuk ke ' . $distribution->location,
                    ]);
                }
            }

            return $distribution->refresh();
        });
    }
    public function deleteDistribution(StockDistributions $distribution): bool
    {
        return DB::transaction(
            function () use ($distribution) {
                // Cegah penghapusan jika distribusi sudah final
                if ($distribution->status && in_array($distribution->status, [
                    DistributionStatus::DELIVERED->value,
                    DistributionStatus::COMPLETED->value,
                ])) {
                    return false;
                }

                // Revert semua mutasi stok
                foreach ($distribution->items as $item) {
                    $batch = $item->batch;
                    $before = $batch->current_quantity;

                    // Kembalikan stok
                    $batch->increment('current_quantity', $item->approved_quantity);

                    // Catat revert
                    StockMutations::create([
                        'warehouse_id'    => $batch->warehouse_id,
                        'batch_id'        => $batch->id,
                        'change_quantity' => -$item->approved_quantity,
                        'before_quantity' => $before,
                        'after_quantity'  => $batch->current_quantity,
                        'reference_type'  => 'DISTRIBUTION_DELETED',
                        'reference_id'    => $distribution->id,
                        'notes'           => 'Distribusi dihapus, stok dikembalikan',
                    ]);
                }

                // Hapus item terlebih dahulu (karena ada FK)
                $distribution->items()->delete();

                // Hapus distribusi
                return $distribution->delete();
            }
        );
    }

}