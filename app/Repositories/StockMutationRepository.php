<?php

namespace App\Repositories;

use App\Models\StockMutations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockMutationRepository
{
    protected function getCompletedStatuses(): array
    {
        return config('dss.completed_statuses', ['SUCCESS']);
    }

    /**
     * Hitung total keluar per produk per gudang (7 hari atau sesuai parameter).
     */
    public function getOutboundMovementByProductWarehouse(int $days): Collection
    {
        return DB::table('stock_mutations')
            ->join('batches', 'stock_mutations.batch_id', '=', 'batches.id')
            ->select([
                'stock_mutations.batch_id',
                'batches.product_id',
                'stock_mutations.warehouse_id',
                DB::raw('SUM(ABS(stock_mutations.change_quantity)) as total_out'),
            ])
            ->whereIn('stock_mutations.reference_type', ['DISTRIBUTION', 'TRANSFER'])
            ->whereIn('stock_mutations.status', $this->getCompletedStatuses())
            ->whereBetween('stock_mutations.created_at', [
                now()->subDays($days)->startOfDay(),
                now()->subDays(1)->endOfDay(),
            ])
            ->groupBy(['stock_mutations.batch_id', 'batches.product_id', 'stock_mutations.warehouse_id'])
            ->get();
    }

    /**
     * Hitung total movement per gudang (volume absolut semua reference_type).
     */
    public function getWarehouseTotalMovement(int $days): Collection
    {
        return DB::table('stock_mutations')
            ->select([
                'warehouse_id',
                DB::raw('SUM(ABS(change_quantity)) as total_movement'),
            ])
            ->whereIn('status', $this->getCompletedStatuses())
            ->whereBetween('created_at', [
                now()->subDays($days)->startOfDay(),
                now()->subDays(1)->endOfDay(),
            ])
            ->groupBy('warehouse_id')
            ->get();
    }

    /**
     * Total barang keluar untuk satu produk di satu gudang dalam beberapa hari terakhir.
     */
    public function getProductWarehouseOutboundMovement(string $productId, string $warehouseId, int $days): int
    {
        return (int) DB::table('stock_mutations')
            ->join('batches', 'stock_mutations.batch_id', '=', 'batches.id')
            ->where('batches.product_id', $productId)
            ->where('stock_mutations.warehouse_id', $warehouseId)
            ->whereIn('stock_mutations.reference_type', ['DISTRIBUTION', 'TRANSFER'])
            ->whereIn('stock_mutations.status', $this->getCompletedStatuses())
            ->whereBetween('stock_mutations.created_at', [
                now()->subDays($days)->startOfDay(),
                now()->subDays(1)->endOfDay(),
            ])
            ->sum(DB::raw('ABS(stock_mutations.change_quantity)'));
    }

    /**
     * Ambil semua batch produk selain gudang tertentu.
     */
    public function getBatchesByProductExcludingWarehouse(string $productId, string $excludedWarehouseId): Collection
    {
        return \App\Models\Batch::with(['warehouse'])
            ->where('product_id', $productId)
            ->where('warehouse_id', '!=', $excludedWarehouseId)
            ->where('current_quantity', '>', 0)
            ->get();
    }

    /**
     * Kecepatan per produk-per-gudang untuk seluruh gudang produk tersebut.
     */
    public function getVelocityByProductWarehouse(string $productId, int $days): Collection
    {
        return StockMutations::query()
            ->join('batches', 'batches.id', '=', 'stock_mutations.batch_id')
            ->select([
                'stock_mutations.warehouse_id',
                DB::raw('SUM(ABS(stock_mutations.change_quantity)) as total_quantity'),
            ])
            ->where('batches.product_id', $productId)
            ->whereIn('stock_mutations.reference_type', ['DISTRIBUTION', 'TRANSFER']) // ← tambah ini
            ->whereIn('stock_mutations.status', $this->getCompletedStatuses())
            ->where('stock_mutations.created_at', '>=', now()->subDays($days))
            ->groupBy('stock_mutations.warehouse_id')
            ->get();
    }
}
