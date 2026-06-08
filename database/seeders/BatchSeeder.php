<?php

namespace Database\Seeders;

use App\Models\PurchaseOrderItem;
use App\Models\ProductReceivingItem;
use App\Models\ProductReceiving;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Services\BatchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BatchSeeder extends Seeder
{
    public function run(BatchService $batchService): void
    {
        // ─── Kode existing tidak diubah ───────────────────────────────
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
                'barcode' => null,
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

        // ─── Tambahan batch dummy untuk variasi DSS ───────────────────
        $this->seedExtraBatches($batchService);
    }

    /**
     * Tambah batch dummy per kombinasi warehouse + product
     * untuk memancing variasi kategori DSS (Fast/Slow/Normal/Dead).
     */
    private function seedExtraBatches(BatchService $batchService): void
    {
        $warehouses     = \App\Models\Warehouse::all();
        $products       = \App\Models\Product::all();
        $dummyReceiving = ProductReceiving::first();

        if ($warehouses->isEmpty() || $products->isEmpty() || !$dummyReceiving) {
            $this->command->warn('Tidak ada warehouse/product/receiving untuk extra batch.');
            return;
        }

        // Variasi qty untuk memancing kategori DSS berbeda
        $qtyVariants = [5, 10, 50, 100, 200, 300, 500];

        $created = 0;

        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                // Skip kalau kombinasi warehouse + product sudah ada
                $exists = Batch::where('warehouse_id', $warehouse->id)
                    ->where('product_id', $product->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $qty = $qtyVariants[array_rand($qtyVariants)];

                $batch = Batch::create([
                    'id'               => (string) Str::ulid(),
                    'batch_code'       => 'BCH-' . $warehouse->warehouse_code . '-' . strtoupper(Str::random(6)),
                    'receiving_id'     => $dummyReceiving->id,
                    'product_id'       => $product->id,
                    'warehouse_id'     => $warehouse->id,
                    'initial_quantity' => $qty,
                    'current_quantity' => $qty,
                    'production_date'  => now()->subMonths(rand(1, 6)),
                    'expired_date'     => now()->addMonths(rand(6, 24)),
                    'price'            => rand(5000, 100000),
                    'condition'        => 'GOOD',
                    'barcode'          => null,
                ]);

                $batchService->generateBarcode($batch);

                StockMutations::create([
                    'id'              => (string) Str::ulid(),
                    'warehouse_id'    => $warehouse->id,
                    'batch_id'        => $batch->id,
                    'change_quantity' => $qty,
                    'before_quantity' => 0,
                    'after_quantity'  => $qty,
                    'reference_type'  => 'RECEIVE',
                    'reference_id'    => $dummyReceiving->id,
                    'notes'           => "DSS Extra Batch: {$product->name} di {$warehouse->name}",
                    'status'          => 'SUCCESS',
                ]);

                $created++;
            }
        }

        $this->command->info("Extra batch DSS: {$created} batch berhasil dibuat.");
    }
}