<?php

namespace App\Repositories;

use App\Models\Batch;
use App\Models\Restock;
use App\Models\StockDistributions;
use App\Models\StockReturns;
use App\Models\StockTransfers;
use App\Models\Warehouse;
use App\Models\ProductReceiving;
use App\Models\RackWarehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\DssEngine;
use stdClass;

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
        if ($hasActivityType('receiving')) {
            $addActivityStats(ProductReceiving::query()
                ->join('purchase_orders', 'product_receivings.purchase_id', '=', 'purchase_orders.id')
                ->selectRaw('purchase_orders.warehouse_id as warehouse_id, COUNT(*) as activity_count, MAX(product_receivings.created_at) as last_activity_at')
                ->when($filters['warehouse_id'] ?? null, fn($query, $warehouseId) => $query->where('purchase_orders.warehouse_id', $warehouseId))
                ->when($filters['product_id'] ?? null, fn($query, $productId) => $query->whereExists(function ($subQuery) use ($productId) {
                    $subQuery->selectRaw('1')
                        ->from('product_receiving_items')
                        ->whereColumn('product_receiving_items.receiving_id', 'product_receivings.id')
                        ->where('product_receiving_items.product_id', $productId);
                }))
                ->when($filters['date_from'] ?? null, fn($query, $dateFrom) => $query->whereDate('product_receivings.created_at', '>=', $dateFrom))
                ->when($filters['date_to'] ?? null, fn($query, $dateTo) => $query->whereDate('product_receivings.created_at', '<=', $dateTo))
                ->groupBy('purchase_orders.warehouse_id')
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

        $dssEngine = app(DssEngine::class);
        $warehouseActivities = $dssEngine->getWarehouseActivities(30);

        return $warehouses->map(function (Warehouse $warehouse) use ($batchStats, $activityStats, $warehouseActivities) {
            $batchStat = $batchStats->get($warehouse->id);
            $activityStat = $activityStats->get($warehouse->id, [
                'activity_count' => 0,
                'last_activity_at' => null,
            ]);

            $dssActivity = $warehouseActivities[$warehouse->id] ?? [
                'activity_score' => 0,
                'warehouse_activity' => 'INACTIVE',
            ];

            return [
                'warehouse_id' => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'region_name' => $warehouse->region?->name,
                'total_batches' => (int) ($batchStat->batch_count ?? 0),
                'total_stock' => (int) ($batchStat->total_stock ?? 0),
                'activity_count' => $activityStat['activity_count'],
                'last_activity_at' => $activityStat['last_activity_at'] ?? $batchStat->last_batch_update_at ?? null,
                'activity_score' => $dssActivity['activity_score'],
                'warehouse_activity' => $dssActivity['warehouse_activity'],
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

        $logs = $logs->merge($this->mapReceivings($filters));
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

    private function mapReceivings(array $filters = []): Collection
    {
        return ProductReceiving::query()
            ->with(['purchase.warehouse', 'items.products', 'user'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->whereHas('purchase', function ($purchaseQuery) use ($warehouseId) {
                    $purchaseQuery->where('warehouse_id', $warehouseId);
                });
            })
            ->when($filters['product_id'] ?? null, function ($query, $productId) {
                $query->whereHas('items', function ($itemQuery) use ($productId) {
                    $itemQuery->where('product_id', $productId);
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
            ->map(function (ProductReceiving $receive) {
                $item = $receive->items->first();
                $product = $item?->products;

                return [
                    'id' => $receive->id,
                    'activity_type' => 'receiving',
                    'title' => 'Receiving ' . $receive->receiving_code,
                    'warehouse_id' => $receive->purchase?->warehouse_id,
                    'warehouse_name' => $receive->purchase?->warehouse?->name,
                    'batch_id' => null,
                    'batch_code' => null,
                    'product_id' => $product?->id,
                    'product_name' => $product?->name,
                    'status' => $receive->status?->value ?? $receive->status,
                    'quantity' => (int) $receive->items->sum('quantity_accepted'),
                    'before_quantity' => null,
                    'after_quantity' => null,
                    'reference_type' => 'receive',
                    'reference_id' => $receive->id,
                    'notes' => null,
                    'activity_at' => $receive->receiving_date ?? $receive->created_at,
                    'payload' => [
                        'receiving_code' => $receive->receiving_code,
                        'purchase_order_code' => $receive->purchase?->po_code,
                        'received_by' => $receive->user?->name,
                        'items_count' => $receive->items->count(),
                    ],
                ];
            });
    }
    public function getChartData($filters)
    {
        // 1. Tentukan rentang waktu 7 hari terakhir
        $startDate = isset($filters['start_date']) ? Carbon::parse($filters['start_date']) : Carbon::now()->subDays(7)->startOfDay();
        $endDate = isset($filters['end_date']) ? Carbon::parse($filters['end_date']) : Carbon::now()->endOfDay();

        // 2. Ambil data dari 4 tabel aktivitas. 
        // Kita gunakan perwakilan kolom 'quantity' (ganti jika nama kolom aslimu berbeda, misal 'qty' atau 'total')
        $restocks = Restock::with('item')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($item) {
                return [
                    'activity_at' => $item->created_at->toIso8601String(),
                    'type' => 'restock',
                    'quantity' => (int) $item->item->sum('requested_quantity')
                ];
            });

        $transfers = StockTransfers::whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($item) {
                return [
                    'activity_at' => $item->created_at->toIso8601String(),
                    'type' => 'transfer',
                    'quantity' => (int) ($item->quantity ?? 0)
                ];
            });

        $distributions = StockDistributions::whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($item) {
                return [
                    'activity_at' => $item->created_at->toIso8601String(),
                    'type' => 'distribution',
                    'quantity' => (int) ($item->quantity ?? 0)
                ];
            });

        $returns = StockReturns::whereBetween('created_at', [$startDate, $endDate])
            ->get()
            ->map(function ($item) {
                return [
                    'activity_at' => $item->created_at->toIso8601String(),
                    'type' => 'return',
                    'quantity' => (int) ($item->quantity ?? 0)
                ];
            });

        // 3. Gabungkan semua data aktivitas menjadi satu array tunggal
        $activities = collect()
            ->merge($restocks)
            ->merge($transfers)
            ->merge($distributions)
            ->merge($returns)
            ->sortByDesc('activity_at')
            ->values()
            ->toArray();

        // 4. Ambil data total stok per gudang
        // Pastikan relasi di model Warehouse kamu bernama 'stocks' (atau sesuaikan jika berbeda)
        $warehouses = Warehouse::select('id', 'name')
            ->withCount(['batches as total_stock' => function ($q) {
                $q->select(DB::raw('coalesce(sum(current_quantity), 0)'));
            }])
            ->get()
            ->map(function ($w) {
                return [
                    'name' => $w->name,
                    'total_stock' => (int) $w->total_stock,
                    'totalStock' => (int) $w->total_stock // double check untuk mengamankan pembacaan di FE
                ];
            })
            ->toArray();

        return [
            'activities' => $activities,
            'warehouses' => $warehouses
        ];
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
                    'status' => $distribution->status?->value ?? $distribution->status,
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
            ->with(['fromWarehouse', 'toWarehouse', 'request', 'confirm', 'products'])
            ->when($filters['warehouse_id'] ?? null, function ($query, $warehouseId) {
                $query->where(function ($warehouseQuery) use ($warehouseId) {
                    $warehouseQuery->where('from_warehouse_id', $warehouseId)
                        ->orWhere('to_warehouse_id', $warehouseId);
                });
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
            ->map(function (StockTransfers $transfer) {
                return [
                    'id'              => $transfer->id,
                    'activity_type'   => 'transfer',
                    'title'           => 'Transfer ' . $transfer->transfer_code,
                    'warehouse_id'    => $transfer->from_warehouse_id,
                    'warehouse_name'  => $transfer->fromWarehouse?->name,
                    'batch_id'        => null,   // tidak ada lagi pivot batch
                    'batch_code'      => null,
                    'product_id'      => $transfer->product_id,
                    'product_name'    => $transfer->products?->name,
                    'status'          => $transfer->status?->value ?? $transfer->status,
                    'quantity'        => (int) ($transfer->approved_quantity ?? $transfer->requested_quantity ?? 0),
                    'before_quantity' => null,
                    'after_quantity'  => null,
                    'reference_type'  => 'transfer',
                    'reference_id'    => $transfer->id,
                    'notes'           => $transfer->notes,
                    'activity_at'     => $transfer->created_at,
                    'payload' => [
                        'from_warehouse' => $transfer->fromWarehouse?->name,
                        'to_warehouse'   => $transfer->toWarehouse?->name,
                        'requested_by'   => $transfer->requested_by,
                        'confirmed_by'   => $transfer->confirmed_by,
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

 /**
     * Get dashboard summary with stock status breakdown
     */
    public function getDashboardSummary(array $filters = []): array
    {
        $userWarehouseId = $filters['warehouse_id'] ?? null;

        // Get all batches for warehouse
        $batches = Batch::query()
            ->with('product')
            ->when($userWarehouseId, fn($q) => $q->where('warehouse_id', $userWarehouseId))
            ->get();

        // Categorize by stock status
        $normal = 0;
        $rendah = 0;
        $kritis = 0;
        $overstock = 0;
        $alerts = [];

        foreach ($batches as $batch) {
            $status = $this->getStockStatus($batch);

            match ($status) {
                'normal' => $normal++,
                'rendah' => $rendah++,
                'kritis' => $kritis++,
                'overstock' => $overstock++,
            };

            // Collect alerts
            if (in_array($status, ['rendah', 'kritis'])) {
                $alerts[] = [
                    'batch_id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'product_id' => $batch->product_id,
                    'product_name' => $batch->product?->name,
                    'current_quantity' => (int) $batch->current_quantity,
                    'status' => $status,
                    'expired_date' => $batch->expired_date,
                    'expired_in_days' => $batch->expired_date ? $batch->expired_date->diffInDays(now()) : null,
                    'warehouse_id' => $batch->warehouse_id,
                ];
            }
        }

        // Calculate Today's Stats
        $today = Carbon::today();

        $stokMasukHariIni = ProductReceiving::whereDate('created_at', $today)->sum(DB::raw('(SELECT SUM(quantity_accepted) FROM product_receiving_items WHERE product_receiving_items.receiving_id = product_receivings.id)'))
            + Restock::whereDate('created_at', $today)->sum(DB::raw('(SELECT SUM(requested_quantity) FROM restock_items WHERE restock_items.restock_id = restocks.id)'))
            + StockReturns::whereDate('created_at', $today)->sum('approved_quantity')
            + StockTransfers::whereDate('created_at', $today)->when($userWarehouseId, fn($q) => $q->where('to_warehouse_id', $userWarehouseId))->sum('approved_quantity');

        $stokKeluarHariIni = StockDistributions::whereDate('created_at', $today)->when($userWarehouseId, fn($q) => $q->where('warehouse_id', $userWarehouseId))->sum(DB::raw('(SELECT SUM(approved_quantity) FROM stock_distribution_items WHERE stock_distribution_items.distribution_id = stock_distributions.id)'))
            + StockTransfers::whereDate('created_at', $today)->when($userWarehouseId, fn($q) => $q->where('from_warehouse_id', $userWarehouseId))->sum('approved_quantity');

        $transaksiSelesaiHariIni = ProductReceiving::whereDate('created_at', $today)->where('status', 'COMPLETED')->count()
            + Restock::whereDate('created_at', $today)->where('status', 'APPROVED')->count()
            + StockReturns::whereDate('created_at', $today)->where('status', 'APPROVED')->count()
            + StockTransfers::whereDate('created_at', $today)->where('status', 'APPROVED')->count()
            + StockDistributions::whereDate('created_at', $today)->where('status', 'APPROVED')->count();

        // Calculate Rack Capacities
        $racks = RackWarehouse::with('locations')
            ->when($userWarehouseId, fn($q) => $q->where('warehouse_id', $userWarehouseId))
            ->get();

        $rackCapacities = $racks->map(function ($rack) {
            $capacity = $rack->locations->sum('capacity');
            $filled = $rack->locations->sum('used');
            $available = $capacity - $filled;
            $utilizationPercent = $capacity > 0 ? round(($filled / $capacity) * 100) : 0;
            
            $status = 'optimal';
            if ($utilizationPercent >= 90) {
                $status = 'critical';
            } elseif ($utilizationPercent >= 75) {
                $status = 'warning';
            }

            return [
                'id' => $rack->id,
                'rakName' => $rack->rack_name,
                'productName' => 'TBD', // Dynamic product detection can be added later
                'capacity' => (int) $capacity,
                'filled' => (int) $filled,
                'available' => (int) $available,
                'utilizationPercent' => $utilizationPercent,
                'status' => $status
            ];
        });

        return [
            'total_sku' => $batches->count(),
            'stock_status' => [
                'normal' => $normal,
                'rendah' => $rendah,
                'kritis' => $kritis,
                'overstock' => $overstock,
            ],
            'alerts' => collect($alerts)
                ->sortBy(fn($a) => $a['status'] === 'kritis' ? 0 : 1)
                ->values()
                ->take(10)
                ->all(),
            'today_stats' => [
                'stok_masuk' => (int) $stokMasukHariIni,
                'stok_keluar' => (int) $stokKeluarHariIni,
                'transaksi_selesai' => $transaksiSelesaiHariIni,
                'avg_processing_time' => 0 // Dummy for now
            ],
            'rack_capacities' => $rackCapacities->values()->toArray()
        ];
    }

    /**
     * Get low stock alerts
     */
    public function getLowStockAlerts(array $filters = []): Collection
    {
        $userWarehouseId = $filters['warehouse_id'] ?? null;

        $batches = Batch::query()
            ->with(['product', 'warehouse'])
            ->when($userWarehouseId, fn($q) => $q->where('warehouse_id', $userWarehouseId))
            ->get();

        return collect($batches)
            ->filter(fn($batch) => in_array($this->getStockStatus($batch), ['rendah', 'kritis']))
            ->map(function (Batch $batch) {
                $status = $this->getStockStatus($batch);
                $expiredInDays = $batch->expired_date ? $batch->expired_date->diffInDays(now()) : null;

                return [
                    'id' => $batch->id,
                    'batch_code' => $batch->batch_code,
                    'sku' => $batch->product?->sku,
                    'product_name' => $batch->product?->name,
                    'current_quantity' => (int) $batch->current_quantity,
                    'warehouse_name' => $batch->warehouse?->name,
                    'location' => 'TBD', // TODO: add rack_location to Batch
                    'status' => $status,
                    'status_label' => match ($status) {
                        'kritis' => 'Kritis',
                        'rendah' => 'Rendah',
                        'akan_expired' => 'Akan Expired',
                        default => 'Normal',
                    },
                    'expired_date' => $batch->expired_date,
                    'expired_in_days' => $expiredInDays,
                    'message' => $this->getAlertMessage($batch, $status, $expiredInDays),
                ];
            })
            ->sortByDesc(fn($a) => $a['status'] === 'kritis' ? 1 : 0)
            ->values();
    }
   
    

   
    private function getStockStatus(Batch $batch): string
    {
        // Check if expired or will expire soon
        if ($batch->expired_date) {
            $daysUntilExpiry = $batch->expired_date->diffInDays(now());
            if ($daysUntilExpiry <= 0) {
                return 'kritis';
            }
            if ($daysUntilExpiry <= 7) {
                return 'rendah';
            }
        }

        // Check quantity thresholds
        $quantity = (int) $batch->current_quantity;

        if ($quantity <= 0) {
            return 'kritis';
        }

        if ($quantity <= 50) {
            return 'rendah';
        }

        if ($quantity > 500) {
            return 'overstock';
        }

        return 'normal';
    }

    /**
     * Generate alert message
     */
    private function getAlertMessage(Batch $batch, string $status, ?int $expiredInDays): string
    {
        return match ($status) {
            'kritis' => $expiredInDays !== null && $expiredInDays <= 0
                ? 'Produk sudah expired! Segera keluarkan dari gudang.'
                : 'Stok sangat rendah! Segera lakukan restok.',
            'rendah' => $expiredInDays !== null && $expiredInDays <= 7
                ? "Akan expired dalam {$expiredInDays} hari."
                : 'Stok mendekati batas minimum.',
            default => 'Status normal',
        };
    }
}
