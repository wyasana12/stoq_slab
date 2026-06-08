<?php

namespace App\Repositories;

use App\Models\Batch;
use App\Models\ProductReceivingItem;
use App\Models\RestockItem;
use App\Models\StockDistributionItem;
use App\Models\StockTransfers;
use App\Models\StockDistributions;
use App\Models\StockReturns;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReportRepository
{
    protected array $fieldLabels = [
        'product_code' => 'Kode Produk',
        'product_name' => 'Nama Produk',
        'category' => 'Kategori',
        'batch_code' => 'Batch',
        'warehouse_name' => 'Lokasi',
        'current_quantity' => 'Qty',
        'production_date' => 'Tanggal Produksi',
        'expired_date' => 'Tanggal Kadaluarsa',
        'price' => 'Harga Satuan',
        'total_value' => 'Total Nilai',
        'movement_date' => 'Tanggal',
        'movement_type' => 'Jenis Pergerakan',
        'tujuan' => 'Tujuan',
        'requested_by' => 'Diminta Oleh',
        'confirmed_by' => 'Dikonfirmasi Oleh',
        'restock_date' => 'Tanggal Restok',
        'distribution_date' => 'Tanggal Distribusi',
        'status' => 'Status',
        'recommendation' => 'Rekomendasi',
        'suggested_qty' => 'Saran Qty',
        'from_warehouse' => 'Dari Gudang',
        'to_warehouse' => 'Ke Gudang',
    ];

    protected array $defaultFields = [
        'stock_current' => [
            'product_code',
            'product_name',
            'category',
            'batch_code',
            'warehouse_name',
            'qty',
            'satuan',
            'nilai',
            'production_date',
            'expired_date',
            'price',
        ],
        'stock_movement' => [
            'movement_date',
            'warehouse_name',
            'movement_type',
            'product_name',
            'qty',
            'satuan',
        ],
        'stock_minimum' => [
            'product_code',
            'product_name',
            'category',
            'batch_code',
            'warehouse_name',
            'qty',
            'satuan',
            'price',
            'nilai',
        ],
        'stock_critical' => [
            'warehouse_name',
            'product_name',
            'category',
            'recommendation',
            'qty',
            'from_warehouse',
        ],
        'stock_batch_expiry' => [
            'product_code',
            'product_name',
            'category',
            'batch_code',
            'warehouse_name',
            'qty',
            'satuan',
            'production_date',
            'expired_date',
        ],
        'stock_value' => [
            'product_code',
            'product_name',
            'category',
            'warehouse_name',
            'qty',
            'satuan',
            'price',
            'nilai',
        ],
        'warehouse_comparison' => [
            'product_code',
            'product_name',
            'category',
            'warehouse_name',
            'qty',
            'satuan',
        ],
    ];

    public function getReportRows(string $template, array $filters = []): Collection
    {
        return match ($template) {
            'stock_current' => $this->getStockCurrent($filters),
            'stock_movement' => $this->getStockMovement($filters),
            'stock_minimum' => $this->getStockMinimum($filters),
            'stock_critical' => $this->getStockCritical($filters),
            'stock_batch_expiry' => $this->getStockBatchExpiry($filters),
            'stock_value' => $this->getStockValue($filters),
            'warehouse_comparison' => $this->getWarehouseComparison($filters),
            default => collect(),
        };
    }

    public function getDefaultFieldsForTemplate(string $template): array
    {
        return $this->defaultFields[$template] ?? ['product_code', 'product_name', 'warehouse_name'];
    }

    public function getFieldHeadings(array $fields): array
    {
        return array_map(fn($field) => $this->fieldLabels[$field] ?? Str::headline($field), $fields);
    }

    protected function applyCommonFilters($query, array $filters)
    {
        $query->when($filters['warehouse_id'] ?? null, fn($q, $warehouseId) => $q->where('warehouse_id', $warehouseId))
            ->when($filters['category_id'] ?? null, fn($q, $categoryId) => $q->whereHas('product', fn($q2) => $q2->where('category_id', $categoryId)))
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->where('product_id', $productId))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereDate('created_at', '<=', $dateTo));

        return $query;
    }

    protected function getStockCurrent(array $filters): Collection
    {
        return $this->applyCommonFilters(
            Batch::query()->with(['product.category', 'product.unit', 'warehouse']),
            $filters
        )
            ->when(!empty($filters['min_stock_threshold']), fn($q, $threshold) => $q->where('current_quantity', '<=', $threshold))
            ->get()
            ->map(fn(Batch $batch) => [
                'product_code' => $batch->product?->sku,
                'product_name' => $batch->product?->name,
                'category' => $batch->product?->category?->name,
                'batch_code' => $batch->batch_code,
                'warehouse_name' => $batch->warehouse?->name,
                'qty' => (int) $batch->current_quantity,
                'current_quantity' => (int) $batch->current_quantity,
                'satuan' => $batch->product?->unit?->symbol,
                'production_date' => $batch->production_date,
                'expired_date' => $batch->expired_date,
                'price' => $batch->price,
                'nilai' => $batch->price * $batch->current_quantity,
            ])->values();
    }

    protected function getStockMovement(array $filters): Collection
    {
        $receivingRows = ProductReceivingItem::query()
            ->with(['products.unit', 'receiving.purchase.warehouse'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->where('product_id', $productId))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereHas('receiving', fn($rq) => $rq->whereDate('created_at', '>=', $dateFrom)))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereHas('receiving', fn($rq) => $rq->whereDate('created_at', '<=', $dateTo)))
            ->get()
            ->map(fn(ProductReceivingItem $item) => [
                'movement_date' => $item->receiving?->created_at,
                'warehouse_name' => $item->receiving?->purchase?->warehouse?->name,
                'movement_type' => 'receiving',
                'product_name' => $item->products?->name,
                'qty' => (int) $item->quantity_accepted,
                'satuan' => $item->products?->unit?->symbol,
            ]);

        $distributionRows = StockDistributionItem::query()
            ->with(['batch.product.unit', 'distribution.warehouse'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->whereHas('batch', fn($bq) => $bq->where('product_id', $productId)))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereHas('distribution', fn($dq) => $dq->whereDate('created_at', '>=', $dateFrom)))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereHas('distribution', fn($dq) => $dq->whereDate('created_at', '<=', $dateTo)))
            ->get()
            ->map(fn(StockDistributionItem $item) => [
                'movement_date' => $item->distribution?->created_at,
                'warehouse_name' => $item->distribution?->warehouse?->name,
                'movement_type' => 'distribution',
                'product_name' => $item->batch?->product?->name,
                'qty' => (int) $item->approved_quantity,
                'satuan' => $item->batch?->product?->unit?->symbol,
            ]);

        $transferRows = StockTransfers::query()
            ->with(['batch.product.unit', 'fromWarehouse'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->whereHas('batch', fn($bq) => $bq->where('product_id', $productId)))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereDate('created_at', '<=', $dateTo))
            ->get()
            ->map(fn(StockTransfers $transfer) => [
                'movement_date' => $transfer->created_at,
                'warehouse_name' => $transfer->fromWarehouse?->name,
                'movement_type' => 'transfer',
                'product_name' => $transfer->products?->name,
                'qty' => 0,
                'satuan' => $transfer->product?->unit?->symbol,
            ]);

        $restockRows = RestockItem::query()
            ->with(['product.unit', 'restock.warehouse'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->where('product_id', $productId))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereHas('restock', fn($rq) => $rq->whereDate('created_at', '>=', $dateFrom)))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereHas('restock', fn($rq) => $rq->whereDate('created_at', '<=', $dateTo)))
            ->get()
            ->map(fn(RestockItem $item) => [
                'movement_date' => $item->restock?->created_at,
                'warehouse_name' => $item->restock?->warehouse?->name,
                'movement_type' => 'restock',
                'product_name' => $item->product?->name,
                'qty' => (int) $item->requested_quantity,
                'satuan' => $item->product?->unit?->symbol,
            ]);

        $returnRows = StockReturns::query()
            ->with(['product.unit', 'warehouse'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->where('product_id', $productId))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereDate('created_at', '<=', $dateTo))
            ->get()
            ->map(fn(StockReturns $item) => [
                'movement_date' => $item->created_at,
                'warehouse_name' => $item->warehouse?->name,
                'movement_type' => 'return',
                'product_name' => $item->product?->name,
                'qty' => (int) $item->approved_quantity,
                'satuan' => $item->product?->unit?->symbol,
            ]);

        return $receivingRows
            ->merge($distributionRows)
            ->merge($transferRows)
            ->merge($restockRows)
            ->merge($returnRows)
            ->sortByDesc('movement_date')
            ->values();;
    }
    protected function getStockMinimum(array $filters): Collection
    {
        return $this->applyCommonFilters(
            Batch::query()->with(['product.category', 'product.unit', 'warehouse']),
            $filters
        )
            ->when(!empty($filters['min_stock_threshold']), fn($q, $threshold) => $q->where('current_quantity', '<=', $threshold))
            ->get()
            ->map(fn(Batch $batch) => [
                'product_code' => $batch->product?->sku,
                'product_name' => $batch->product?->name,
                'category' => $batch->product?->category?->name,
                'batch_code' => $batch->batch_code,
                'warehouse_name' => $batch->warehouse?->name,
                'qty' => (int) $batch->current_quantity,
                'current_quantity' => (int) $batch->current_quantity,
                'satuan' => $batch->product?->unit?->symbol,
                'price' => $batch->price,
                'nilai' => $batch->price * $batch->current_quantity,
            ])->values();
    }

    protected function getStockCritical(array $filters): Collection
    {
        $dssCache = app(\App\Services\DssCacheService::class);
        $days = (int) ($filters['days'] ?? config('dss.default_history_days', 30));
        
        $data = collect($dssCache->getRecommendation($days));

        if (!empty($filters['warehouse_id'])) {
            $data = $data->where('warehouse_id', $filters['warehouse_id']);
        }

        return $data->filter(fn($item) => in_array($item['recommendation'], ['RESTOCK', 'TRANSFER_IN']))
            ->map(function ($item) {
                return [
                    'warehouse_name' => $item['warehouse_name'] ?? '-',
                    'product_name' => $item['product_name'] ?? '-',
                    'category' => $item['category'] ?? '-',
                    'recommendation' => $item['recommendation'] ?? '-',
                    'qty' => $item['suggested_qty'] ?? 0,
                    'from_warehouse' => $item['from_warehouse'] ?? '-',
                ];
            })->values();
    }

    protected function getStockBatchExpiry(array $filters): Collection
    {
        return $this->applyCommonFilters(
            Batch::query()->with(['product.category', 'product.unit', 'warehouse']),
            $filters
        )
            ->whereNotNull('expired_date')
            ->orderBy('expired_date', 'asc')
            ->get()
            ->map(fn(Batch $batch) => [
                'product_code' => $batch->product?->sku,
                'product_name' => $batch->product?->name,
                'category' => $batch->product?->category?->name,
                'batch_code' => $batch->batch_code,
                'warehouse_name' => $batch->warehouse?->name,
                'qty' => (int) $batch->current_quantity,
                'current_quantity' => (int) $batch->current_quantity,
                'satuan' => $batch->product?->unit?->symbol,
                'production_date' => $batch->production_date,
                'expired_date' => $batch->expired_date,
            ])->values();
    }

    protected function getStockValue(array $filters): Collection
    {
        $batches = $this->applyCommonFilters(
            Batch::query()->with(['product.category', 'product.unit', 'warehouse']),
            $filters
        )->get();

        return $batches->groupBy(fn($batch) => $batch->product_id . '-' . $batch->warehouse_id)
            ->map(function ($groupedBatches) {
                $firstBatch = $groupedBatches->first();
                $totalQty = $groupedBatches->sum('current_quantity');
                $totalValue = $groupedBatches->sum(fn($b) => $b->price * $b->current_quantity);
                
                return [
                    'product_code' => $firstBatch->product?->sku,
                    'product_name' => $firstBatch->product?->name,
                    'category' => $firstBatch->product?->category?->name,
                    'warehouse_name' => $firstBatch->warehouse?->name,
                    'qty' => (int) $totalQty,
                    'current_quantity' => (int) $totalQty,
                    'satuan' => $firstBatch->product?->unit?->symbol,
                    'price' => $firstBatch->product?->price ?? $firstBatch->price, // approx
                    'nilai' => $totalValue,
                ];
            })->values();
    }

    protected function getWarehouseComparison(array $filters): Collection
    {
        $batches = $this->applyCommonFilters(
            Batch::query()->with(['product.category', 'product.unit', 'warehouse']),
            $filters
        )->get();

        return $batches->groupBy(fn($batch) => $batch->product_id . '-' . $batch->warehouse_id)
            ->map(function ($groupedBatches) {
                $firstBatch = $groupedBatches->first();
                $totalQty = $groupedBatches->sum('current_quantity');
                
                return [
                    'product_code' => $firstBatch->product?->sku,
                    'product_name' => $firstBatch->product?->name,
                    'category' => $firstBatch->product?->category?->name,
                    'warehouse_name' => $firstBatch->warehouse?->name,
                    'qty' => (int) $totalQty,
                    'current_quantity' => (int) $totalQty,
                    'satuan' => $firstBatch->product?->unit?->symbol,
                ];
            })->values();
    }
}
