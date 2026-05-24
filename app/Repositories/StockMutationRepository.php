<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockMutationRepository
{
    /**
     * Mengambil status mutasi yang sudah sukses/selesai.
     */
    protected function getCompletedStatuses(): array
    {
        return ['SUCCESS', 'DISTRIBUTION_COMPLETED'];
    }

    /**
     * Mengambil TOTAL SELURUH PERGERAKAN (In & Out) untuk rata-rata 7 hari lalu.
     */
    public function getOutboundVelocityByBatchWarehouse(int $days): Collection
    {
        return DB::table('stock_mutations')
            ->select([
                'batch_id',
                'warehouse_id',
                DB::raw('SUM(ABS(change_quantity)) as total_quantity'),
                DB::raw('COUNT(*) as transaction_count')
            ])
            ->whereIn('status', $this->getCompletedStatuses())
            ->whereIn('reference_type', ['DISTRIBUTION', 'RETURN', 'TRANSFER', 'RESTOCK', 'RECEIVE'])
            ->whereBetween('created_at', [
                now()->subDays($days)->startOfDay(),
                now()->subDays(1)->endOfDay()
            ])
            ->groupBy(['batch_id', 'warehouse_id'])
            ->get();
    }

    /**
     * Mengambil data pergerakan aktual khusus HARI INI (Hari ke-8)
     */
    public function getActualDay8Movement(): Collection
    {
        return DB::table('stock_mutations')
            ->select([
                'batch_id',
                'warehouse_id',
                DB::raw('SUM(ABS(change_quantity)) as total_quantity')
            ])
            ->whereIn('status', $this->getCompletedStatuses())
            ->whereIn('reference_type', ['DISTRIBUTION', 'RETURN', 'TRANSFER', 'RESTOCK', 'RECEIVE'])
            ->whereBetween('created_at', [
                now()->startOfDay(),
                now()->endOfDay()
            ])
            ->groupBy(['batch_id', 'warehouse_id'])
            ->get();
    }

    /**
     * Method untuk pencarian spesifik produk (digunakan oleh analyzeByProduct)
     */
    public function getVelocityByProductWarehouse(string $productId, int $days): Collection
    {
        return DB::table('stock_mutations')
            // PERBAIKAN: Hubungkan ke tabel batches karena product_id ada di sana
            ->join('batches', 'stock_mutations.batch_id', '=', 'batches.id')
            ->select([
                'stock_mutations.warehouse_id',
                DB::raw('SUM(ABS(stock_mutations.change_quantity)) as total_quantity')
            ])
            ->where('batches.product_id', $productId) // Menyaring product_id lewat tabel batches
            ->whereIn('stock_mutations.status', $this->getCompletedStatuses())
            ->whereBetween('stock_mutations.created_at', [
                now()->subDays($days)->startOfDay(),
                now()->subDays(1)->endOfDay()
            ])
            ->groupBy('stock_mutations.warehouse_id')
            ->get();
    }
    public function getBatchesByProductExcludingWarehouse(string $productId, string $excludedWarehouseId):Collection
    {
        return \App\Models\Batch::with(['warehouse'])
            ->where('product_id', $productId)
            ->where('warehouse_id', '!=', $excludedWarehouseId)
            ->where('current_quantity', '>', 0) // Hanya ambil gudang yang masih ada stoknya
            ->get();
    }
}
