<?php

namespace App\Repositories;

use App\Models\Batch;
use App\Models\Restock;
use App\Models\StockDistributions;
use App\Models\StockMutations;
use App\Models\StockReturns;
use App\Models\StockTransfers;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class MonitoringRepository
{


    public function getWarehouseSummary(array $filters = []): Collection
    {
        $warehouses = Warehouse::query()
            ->with('region')
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->whereKey($warehouseId);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('name', 'like', '%' . $search . '%');
            })
            ->orderBy('name')
            ->get();

        $batchStats = Batch::query()
            ->selectRaw('warehouse_id, COUNT(*) as batch_count, COALESCE(SUM(current_quantity), 0) as total_stock, MAX(updated_at) as last_batch_update_at')
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['batch_id'] ?? null, function ($query, $batchId) {
                $query->whereKey($batchId);
            })
            ->when($filters['product_id'] ?? null, function ($query, $productId) {
                $query->where('product_id', $productId);
            })
            ->groupBy('warehouse_id')
            ->get()
            ->keyBy('warehouse_id');

        $activityStats = collect();

        $addActivityStats = function ($rows) use ($activityStats) {
            foreach ($rows as $row) {
                if (! $row->warehouse_id) {
                    continue;
                }

                $existing = $activityStats->get($row->warehouse_id, [
                    'activity_count' => 0,
                    'last_activity_at' => null,
                ]);

                $existing['activity_count'] += (int) $row->activity_count;

                if (
                    $existing['last_activity_at'] === null ||
                    Carbon::parse($row->last_activity_at)->gt(Carbon::parse($existing['last_activity_at']))
                ) {
                    $existing['last_activity_at'] = $row->last_activity_at;
                }

                $activityStats->put($row->warehouse_id, $existing);
            }
        };

        $hasActivityType = function (string $type) use ($filters): bool {
            return empty($filters['activity_type']) || $filters['activity_type'] === $type;
        };

        if ($hasActivityType('stock_mutation')) {
            $addActivityStats(StockMutations::query()
                ->selectRaw('warehouse_id, COUNT(*) as activity_count, MAX(created_at) as last_activity_at')
                ->when($filters['warehouse_id'] ?? null, fn($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
                ->when($filters['batch_id'] ?? null, fn($query, $batchId) => $query->where('batch_id', $batchId))
                ->when($filters['product_id'] ?? null, fn($query, $productId) => $query->whereHas('batch', fn($q) => $q->where('product_id', $productId)))
                ->when($filters['date_from'] ?? null, fn($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
                ->when($filters['date_to'] ?? null, fn($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
                ->groupBy('warehouse_id')
                ->get());
        }

        if ($hasActivityType('distribution')) {
            $addActivityStats(StockDistributions::query()
                ->selectRaw('warehouse_id, COUNT(*) as activity_count, MAX(created_at) as last_activity_at')
                ->when($filters['warehouse_id'] ?? null, fn($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
                ->when($filters['batch_id'] ?? null, fn($query, $batchId) => $query->whereHas('items', fn($itemQuery) => $itemQuery->where('batch_id', $batchId)))
                ->when($filters['product_id'] ?? null, fn($query, $productId) => $query->whereHas('items.batch', fn($itemQuery) => $itemQuery->where('product_id', $productId)))
                ->when($filters['date_from'] ?? null, fn($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
                ->when($filters['date_to'] ?? null, fn($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
                ->groupBy('warehouse_id')
                ->get());
        }

        if ($hasActivityType('restock')) {
            $addActivityStats(Restock::query()
                ->selectRaw('warehouse_id, COUNT(*) as activity_count, MAX(created_at) as last_activity_at')
                ->when($filters['warehouse_id'] ?? null, fn($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
                ->when($filters['product_id'] ?? null, fn($query, $productId) => $query->whereHas('item', fn($itemQuery) => $itemQuery->where('product_id', $productId)))
                ->when($filters['date_from'] ?? null, fn($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
                ->when($filters['date_to'] ?? null, fn($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
                ->groupBy('warehouse_id')
                ->get());
        }

        if ($hasActivityType('return')) {
            $addActivityStats(StockReturns::query()
                ->selectRaw('warehouse_id, COUNT(*) as activity_count, MAX(created_at) as last_activity_at')
                ->when($filters['warehouse_id'] ?? null, fn($query, $warehouseId) => $query->where('warehouse_id', $warehouseId))
                ->when($filters['product_id'] ?? null, fn($query, $productId) => $query->where('product_id', $productId))
                ->when($filters['date_from'] ?? null, fn($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
                ->when($filters['date_to'] ?? null, fn($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
                ->groupBy('warehouse_id')
                ->get());
        }

        if ($hasActivityType('transfer')) {
            $addActivityStats(StockTransfers::query()
                ->selectRaw('from_warehouse_id as warehouse_id, COUNT(*) as activity_count, MAX(created_at) as last_activity_at')
                ->when($filters['warehouse_id'] ?? null, fn($query, $warehouseId) => $query->where('from_warehouse_id', $warehouseId))
                ->when($filters['batch_id'] ?? null, fn($query, $batchId) => $query->whereHas('item', fn($itemQuery) => $itemQuery->where('batch_id', $batchId)))
                ->when($filters['product_id'] ?? null, fn($query, $productId) => $query->whereHas('item.batch', fn($itemQuery) => $itemQuery->where('product_id', $productId)))
                ->when($filters['date_from'] ?? null, fn($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
                ->when($filters['date_to'] ?? null, fn($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
                ->groupBy('from_warehouse_id')
                ->get());

            $addActivityStats(StockTransfers::query()
                ->selectRaw('to_warehouse_id as warehouse_id, COUNT(*) as activity_count, MAX(created_at) as last_activity_at')
                ->when($filters['warehouse_id'] ?? null, fn($query, $warehouseId) => $query->where('to_warehouse_id', $warehouseId))
                ->when($filters['batch_id'] ?? null, fn($query, $batchId) => $query->whereHas('item', fn($itemQuery) => $itemQuery->where('batch_id', $batchId)))
                ->when($filters['product_id'] ?? null, fn($query, $productId) => $query->whereHas('item.batch', fn($itemQuery) => $itemQuery->where('product_id', $productId)))
                ->when($filters['date_from'] ?? null, fn($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
                ->when($filters['date_to'] ?? null, fn($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
                ->groupBy('to_warehouse_id')
                ->get());
        }

        return $warehouses->map(function (Warehouse $warehouse) use ($batchStats, $activityStats) {
            $batchStat = $batchStats->get($warehouse->id);
            $activityStat = $activityStats->get($warehouse->id, [
                'activity_count' => 0,
                'last_activity_at' => null,
            ]);

            return [
                'warehouse_id' => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'region_name' => $warehouse->region?->name,
                'total_batches' => (int) ($batchStat->batch_count ?? 0),
                'total_stock' => (int) ($batchStat->total_stock ?? 0),
                'activity_count' => $activityStat['activity_count'],
                'last_activity_at' => $activityStat['last_activity_at'] ?? $batchStat->last_batch_update_at ?? null,
            ];
        })->values();
    }

    public function getBatchDetails(array $filters = []): Collection
    {
        return Batch::query()
            ->with(['warehouse.region', 'product'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['batch_id'] ?? null, function ($query, $batchId) {
                $query->whereKey($batchId);
            })
            ->when($filters['product_id'] ?? null, function ($query, $productId) {
                $query->where('product_id', $productId);
            })
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where('batch_code', 'like', '%' . $search . '%');
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Batch $batch) {
                return [
                    'batch_id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'warehouse_id' => $batch->warehouse_id,
                    'warehouse_name' => $batch->warehouse?->name,
                    'region_name' => $batch->warehouse?->region?->name,
                    'product_id' => $batch->product_id,
                    'product_name' => $batch->product?->name,
                    'current_quantity' => (int) $batch->current_quantity,
                    'production_date' => $batch->production_date,
                    'expired_date' => $batch->expired_date,
                    'created_at' => $batch->created_at,
                    'updated_at' => $batch->updated_at,
                ];
            })
            ->values();
    }

    public function getActivityLog(array $filters = []): Collection
    {
        $logs = collect();

        $logs = $logs->merge($this->mapStockMutations($filters));
        $logs = $logs->merge($this->mapDistributions($filters));
        $logs = $logs->merge($this->mapTransfers($filters));
        $logs = $logs->merge($this->mapRestocks($filters));
        $logs = $logs->merge($this->mapReturns($filters));

        return $logs
            ->filter(function (array $log) use ($filters) {
                if (! empty($filters['warehouse_id']) && $log['warehouse_id'] !== $filters['warehouse_id']) {
                    return false;
                }

                if (! empty($filters['batch_id']) && $log['batch_id'] !== $filters['batch_id']) {
                    return false;
                }

                if (! empty($filters['product_id']) && $log['product_id'] !== $filters['product_id']) {
                    return false;
                }

                if (! empty($filters['activity_type']) && $log['activity_type'] !== $filters['activity_type']) {
                    return false;
                }

                if (! empty($filters['status']) && $log['status'] !== $filters['status']) {
                    return false;
                }

                $activityAt = Carbon::parse($log['activity_at']);

                if (! empty($filters['date_from']) && $activityAt->lt(Carbon::parse($filters['date_from'])->startOfDay())) {
                    return false;
                }

                if (! empty($filters['date_to']) && $activityAt->gt(Carbon::parse($filters['date_to'])->endOfDay())) {
                    return false;
                }

                return true;
            })
            ->sortByDesc('activity_at')
            ->values();
    }

    public function exportCsvRows(array $filters = []): array
    {
        return $this->getActivityLog($filters)->map(function (array $row) {
            return [
                'activity_type' => $row['activity_type'],
                'warehouse_name' => $row['warehouse_name'],
                'batch_code' => $row['batch_code'],
                'product_name' => $row['product_name'],
                'status' => $row['status'],
                'quantity' => $row['quantity'],
                'before_quantity' => $row['before_quantity'],
                'after_quantity' => $row['after_quantity'],
                'reference_type' => $row['reference_type'],
                'reference_id' => $row['reference_id'],
                'notes' => $row['notes'],
                'activity_at' => $row['activity_at'],
            ];
        })->all();
    }

    private function mapStockMutations(array $filters = []): Collection
    {
        return StockMutations::query()
            ->with(['warehouse', 'batch.product'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['batch_id'] ?? null, function ($query, $batchId) {
                $query->where('batch_id', $batchId);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->latest()
            ->get()
            ->map(function (StockMutations $mutation) {
                return [
                    'id' => $mutation->id,
                    'activity_type' => 'stock_mutation',
                    'title' => 'Stock mutation',
                    'warehouse_id' => $mutation->warehouse_id,
                    'warehouse_name' => $mutation->warehouse?->name,
                    'batch_id' => $mutation->batch_id,
                    'batch_code' => $mutation->batch?->batch_code,
                    'product_id' => $mutation->batch?->product_id,
                    'product_name' => $mutation->batch?->product?->name,
                    'status' => $mutation->status,
                    'quantity' => (int) $mutation->change_quantity,
                    'before_quantity' => (int) $mutation->before_quantity,
                    'after_quantity' => (int) $mutation->after_quantity,
                    'reference_type' => $mutation->reference_type,
                    'reference_id' => $mutation->reference_id,
                    'notes' => $mutation->notes,
                    'activity_at' => $mutation->created_at,
                    'payload' => $mutation->toArray(),
                ];
            });
    }

    private function mapDistributions(array $filters = []): Collection
    {
        return StockDistributions::query()
            ->with(['items.batch.product', 'warehouse', 'request', 'confirmedBy'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['batch_id'] ?? null, function ($query, $batchId) {
                $query->whereHas('items', function ($itemQuery) use ($batchId) {
                    $itemQuery->where('batch_id', $batchId);
                });
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->latest()
            ->get()
            ->map(function (StockDistributions $distribution) {
                $items = $distribution->items;

                return [
                    'id' => $distribution->id,
                    'activity_type' => 'distribution',
                    'title' => 'Distribution ' . $distribution->distribution_code,
                    'warehouse_id' => $distribution->warehouse_id,
                    'warehouse_name' => $distribution->warehouse?->name,
                    'batch_id' => $items->first()?->batch_id,
                    'batch_code' => $items->first()?->batch?->batch_code,
                    'product_id' => $items->first()?->batch?->product_id,
                    'product_name' => $items->first()?->batch?->product?->name,
                    'status' => $distribution->status,
                    'quantity' => (int) $items->sum('approved_quantity'),
                    'before_quantity' => null,
                    'after_quantity' => null,
                    'reference_type' => 'distribution',
                    'reference_id' => $distribution->id,
                    'notes' => $distribution->notes,
                    'activity_at' => $distribution->created_at,
                    'payload' => [
                        'distribution_code' => $distribution->distribution_code,
                        'requested_by' => $distribution->requested_by,
                        'confirmed_by' => $distribution->confirmed_by,
                        'items_count' => $items->count(),
                    ],
                ];
            });
    }

    private function mapTransfers(array $filters = []): Collection
    {
        return StockTransfers::query()
            ->with(['item.batch.product', 'fromWarehouse', 'toWarehouse', 'request', 'confirm'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where(function ($warehouseQuery) use ($warehouseId) {
                    $warehouseQuery->where('from_warehouse_id', $warehouseId)
                        ->orWhere('to_warehouse_id', $warehouseId);
                });
            })
            ->when($filters['batch_id'] ?? null, function ($query, $batchId) {
                $query->whereHas('item', function ($itemQuery) use ($batchId) {
                    $itemQuery->where('batch_id', $batchId);
                });
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->latest()
            ->get()
            ->map(function (StockTransfers $transfer) {
                $item = $transfer->item->first();

                return [
                    'id' => $transfer->id,
                    'activity_type' => 'transfer',
                    'title' => 'Transfer ' . $transfer->id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'warehouse_name' => $transfer->fromWarehouse?->name,
                    'batch_id' => $item?->batch_id,
                    'batch_code' => $item?->batch?->batch_code,
                    'product_id' => $item?->batch?->product_id,
                    'product_name' => $item?->batch?->product?->name,
                    'status' => $transfer->status,
                    'quantity' => (int) $transfer->item->sum('quantity'),
                    'before_quantity' => null,
                    'after_quantity' => null,
                    'reference_type' => 'transfer',
                    'reference_id' => $transfer->id,
                    'notes' => null,
                    'activity_at' => $transfer->created_at,
                    'payload' => [
                        'from_warehouse' => $transfer->fromWarehouse?->name,
                        'to_warehouse' => $transfer->toWarehouse?->name,
                        'requested_by' => $transfer->requested_by,
                        'confirmed_by' => $transfer->confirmed_by,
                        'items_count' => $transfer->item->count(),
                    ],
                ];
            });
    }

    private function mapRestocks(array $filters = []): Collection
    {
        return Restock::query()
            ->with(['item.product', 'warehouse', 'request', 'confirm'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->latest()
            ->get()
            ->map(function (Restock $restock) {
                $item = $restock->item->first();

                return [
                    'id' => $restock->id,
                    'activity_type' => 'restock',
                    'title' => 'Restock ' . $restock->id,
                    'warehouse_id' => $restock->warehouse_id,
                    'warehouse_name' => $restock->warehouse?->name,
                    'batch_id' => null,
                    'batch_code' => null,
                    'product_id' => $item?->product_id,
                    'product_name' => $item?->product?->name,
                    'status' => $restock->status?->value ?? $restock->status,
                    'quantity' => (int) $restock->item->sum('requested_quantity'),
                    'before_quantity' => null,
                    'after_quantity' => null,
                    'reference_type' => 'restock',
                    'reference_id' => $restock->id,
                    'notes' => $restock->notes,
                    'activity_at' => $restock->created_at,
                    'payload' => [
                        'requested_by' => $restock->requested_by,
                        'confirmed_by' => $restock->confirmed_by,
                        'items_count' => $restock->item->count(),
                    ],
                ];
            });
    }

    private function mapReturns(array $filters = []): Collection
    {
        return StockReturns::query()
            ->with(['warehouse', 'product', 'request', 'confirm'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->when($filters['product_id'] ?? null, function ($query, $productId) {
                $query->where('product_id', $productId);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            })
            ->latest()
            ->get()
            ->map(function (StockReturns $return) {
                return [
                    'id' => $return->id,
                    'activity_type' => 'return',
                    'title' => 'Return ' . $return->id,
                    'warehouse_id' => $return->warehouse_id,
                    'warehouse_name' => $return->warehouse?->name,
                    'batch_id' => null,
                    'batch_code' => null,
                    'product_id' => $return->product_id,
                    'product_name' => $return->product?->name,
                    'status' => $return->status?->value ?? $return->status,
                    'quantity' => (int) $return->approved_quantity,
                    'before_quantity' => null,
                    'after_quantity' => null,
                    'reference_type' => 'return',
                    'reference_id' => $return->id,
                    'notes' => $return->notes ?? null,
                    'activity_at' => $return->created_at,
                    'payload' => [
                        'requested_by' => $return->requested_by,
                        'confirmed_by' => $return->confirmed_by,
                        'receiving_id' => $return->receiving_id,
                    ],
                ];
            });
    }
}
