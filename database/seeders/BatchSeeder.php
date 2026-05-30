<?php

namespace Database\Seeders;

use App\Models\PurchaseOrderItem;
use App\Models\ProductReceivingItem;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Services\BatchService; // Tambahkan import ini
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BatchSeeder extends Seeder
{
    /**
     * Inject BatchService ke dalam method run
     */
    public function run(BatchService $batchService): void
    {
        $receivingItems = ProductReceivingItem::with([
            'receiving.purchase.warehouse'
        ])->get();

        foreach ($receivingItems as $item) {

            if ($item->quantity_accepted <= 0) {
                continue;
            }

            $receiving = $item->receiving;
            $purchase = $receiving->purchase;
            $warehouse = $purchase->warehouse;

            $poItem = PurchaseOrderItem::where('purchase_id', $purchase->id)
                ->where('product_id', $item->product_id)
                ->first();

            $batch = Batch::create([
                'id' => (string) Str::ulid(),
                'batch_code' => 'BCH-' . $warehouse->warehouse_code . '-' . strtoupper(Str::random(6)),
                'receiving_id' => $receiving->id,
                'product_id' => $item->product_id,
                'warehouse_id' => $warehouse->id,
                'initial_quantity' => $item->quantity_accepted,
                'current_quantity' => $item->quantity_accepted,
                'production_date' => $item->production_date ?? now()->subMonth(),
                'expired_date' => $item->expired_date ?? now()->addYear(),
                'price' => $poItem?->unit_price ?? 0,
                'condition' => $item->condition ?? 'GOOD',
                'barcode' => null, // Biarkan null, akan diisi oleh service
            ]);

            $batchService->generateBarcode($batch);

            StockMutations::create([
                'id' => (string) Str::ulid(),
                'warehouse_id' => $warehouse->id,
                'batch_id' => $batch->id,
                'change_quantity' => $item->quantity_accepted,
                'before_quantity' => 0,
                'after_quantity' => $item->quantity_accepted,
                'reference_type' => 'RECEIVE',
                'reference_id' => $receiving->id,
                'notes' => "Received product {$purchase->po_code} to Warehouse {$warehouse->name}",
                'status' => 'SUCCESS',
            ]);
        }
    }
}