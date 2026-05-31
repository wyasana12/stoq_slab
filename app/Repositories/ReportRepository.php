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
            'movement_type',
            'product_code',
            'product_name',
            'from_warehouse',
            'tujuan',
            'qty',
            'satuan',
            'status',
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
    ];

    public function getReportRows(string $template, array $filters = []): Collection
    {
        return match ($template) {
            'stock_current' => $this->getStockCurrent($filters),
            'stock_movement' => $this->getStockMovement($filters),
            'stock_minimum' => $this->getStockMinimum($filters),
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
                'movement_type' => 'receiving',
                'product_code' => $item->products?->sku,
                'product_name' => $item->products?->name,
                'tujuan' => $item->receiving?->purchase?->warehouse?->name,
                'qty' => (int) $item->quantity_accepted,
                'current_quantity' => (int) $item->quantity_accepted,
                'satuan' => $item->products?->unit?->symbol,
                'status' => $item->receiving?->status?->value ?? $item->receiving?->status,
            ]);

        $distributionRows = StockDistributionItem::query()
            ->with(['batch.product.unit', 'distribution'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->whereHas('batch', fn($bq) => $bq->where('product_id', $productId)))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereHas('distribution', fn($dq) => $dq->whereDate('created_at', '>=', $dateFrom)))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereHas('distribution', fn($dq) => $dq->whereDate('created_at', '<=', $dateTo)))
            ->get()
            ->map(fn(StockDistributionItem $item) => [
                'movement_date' => $item->distribution?->created_at,
                'movement_type' => 'distribution',
                'product_code' => $item->batch?->product?->sku,
                'product_name' => $item->batch?->product?->name,
                'warehouse_name' => $item->distribution?->warehouse?->name,
                'tujuan' => $item->distribution?->outlet_name
                    ? trim($item->distribution?->outlet_name . ' - ' . $item->distribution?->outlet_address)
                    : $item->distribution?->warehouse?->name,
                'qty' => (int) $item->approved_quantity,
                'current_quantity' => (int) $item->approved_quantity,
                'satuan' => $item->batch?->product?->unit?->symbol,
                'status' => $item->distribution?->status?->value ?? $item->distribution?->status,
            ]);
        $transferRows = StockTransfers::query()
            ->with(['batch.product.unit', 'toWarehouse'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->whereHas('batch', fn($bq) => $bq->where('product_id', $productId)))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereDate('created_at', '<=', $dateTo))
            ->get()
            ->map(fn(StockTransfers $transfer) => [
                'movement_date' => $transfer->created_at,
                'movement_type' => 'transfer',
                'product_code' => $transfer->products?->sku,
                'product_name' => $transfer->products?->name,
                'from_warehouse' => $transfer->fromWarehouse?->name,
                'tujuan' => $transfer->toWarehouse?->name,
                'qty' => 0, // jika qty berasal dari item, map lagi sesuai struktur item transfer
                'current_quantity' => 0,
                'satuan' => null,
                'status' => $transfer->status?->value ?? $transfer->status,
            ]);
        $restockRows = RestockItem::query()
            ->with(['product.unit', 'restock.warehouse'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->where('product_id', $productId))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereHas('restock', fn($rq) => $rq->whereDate('created_at', '>=', $dateFrom)))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereHas('restock', fn($rq) => $rq->whereDate('created_at', '<=', $dateTo)))
            ->get()
            ->map(fn(RestockItem $item) => [
                'movement_date' => $item->restock?->created_at,
                'movement_type' => 'restock',
                'product_code' => $item->product?->sku,
                'product_name' => $item->product?->name,
                'tujuan' => $item->restock?->warehouse?->name,
                'qty' => (int) $item->requested_quantity,
                'current_quantity' => (int) $item->requested_quantity,
                'satuan' => $item->product?->unit?->symbol,
                'status' => $item->restock?->status?->value ?? $item->restock?->status,
            ]);
        $returnRows = StockReturns::query()
            ->with(['product.unit', 'warehouse'])
            ->when($filters['product_id'] ?? null, fn($q, $productId) => $q->where('product_id', $productId))
            ->when($filters['date_from'] ?? null, fn($q, $dateFrom) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn($q, $dateTo) => $q->whereDate('created_at', '<=', $dateTo))
            ->get()
            ->map(fn(StockReturns $item) => [
                'movement_date' => $item->created_at,
                'movement_type' => 'return',
                'product_code' => $item->product?->sku,
                'product_name' => $item->product?->name,
                'from_warehouse' => $item->warehouse?->name,
                'tujuan' => $item->warehouse?->name,
                'qty' => (int) $item->approved_quantity,
                'current_quantity' => (int) $item->approved_quantity,
                'satuan' => $item->product?->unit?->symbol,
                'status' => $item->status?->value ?? $item->status,
            ]);

        return $receivingRows
            ->merge($distributionRows)
            ->merge($transferRows)
            ->merge($restockRows)
            ->merge($returnRows)
            ->sortByDesc('movement_date')
            ->values();
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

    
}
