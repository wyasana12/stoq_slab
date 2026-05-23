<?php

namespace App\Repositories;

use App\Models\Batch;
use App\Models\StockMutations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockMutationRepository
{
    /**
     * Ambil completed statuses dari config.
     * Key: 'completed_statuses' (sesuai dss.php)
     */
    public function getCompletedStatuses(): array
    {
        return config('dss.completed_statuses', []);
    }

    /**
     * Hitung total qty keluar per batch per gudang
     * dalam N hari terakhir — untuk kalkulasi velocity.
     *
     * Hanya ambil DISTRIBUTION & TRANSFER (outbound),
     * karena velocity = seberapa cepat barang keluar.
     */
    public function getVelocityByBatchWarehouse(?int $days = null): Collection
    {
        $query = StockMutations::query()
            ->select([
                'batch_id',
                'warehouse_id',
                DB::raw('SUM(ABS(change_quantity)) as total_quantity'),
            ])
            ->whereIn('status', $this->getCompletedStatuses())
            ->whereIn('reference_type', ['DISTRIBUTION', 'RETURN']) // hanya outbound            
            ->groupBy(['batch_id', 'warehouse_id']);

        if ($days) {
            $query->where('created_at', '>=', now()->subDays($days));
        }

        return $query->get();
    }

    public function getBatchesByProductExcludingWarehouse(
        string $productId,
        string $excludeWarehouseId
    ): Collection {
        return Batch::with(['warehouse'])
            ->where('product_id', $productId)
            ->where('warehouse_id', '!=', $excludeWarehouseId)
            ->get();
    }

    public function getVelocityByProductWarehouse(
        string $productId,
        int $days
    ): Collection {
        return StockMutations::query()
            ->select([
                'warehouse_id',
                DB::raw('SUM(ABS(change_quantity)) as total_quantity'),
            ])
            ->whereIn('status', $this->getCompletedStatuses())
            ->whereIn('reference_type', ['DISTRIBUTION', 'RETURN'])
            ->where('created_at', '>=', now()->subDays($days))
            ->whereHas('batch', fn($q) => $q->where('product_id', $productId))
            ->groupBy('warehouse_id')
            ->get();
    }

    /**
     * Ambil batch beserta relasi product dan warehouse.
     */
    public function getBatchById(string $batchId): ?Batch
    {
        return Batch::with(['product', 'warehouse'])->find($batchId);
    }

    /**
     * Ambil semua batch aktif beserta current_quantity-nya.
     * Dipakai engine untuk cari kandidat transfer lintas gudang.
     */
    public function getAllBatchesWithStock(): Collection
    {
        return Batch::with(['product', 'warehouse'])
            ->where('current_quantity', '>', 0)
            ->get();
    }
}
