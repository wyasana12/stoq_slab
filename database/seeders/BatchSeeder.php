<?php

namespace Database\Seeders;

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
        // 1. Load data dengan relasi polymorphic 'receivable'
        $receivingItems = ProductReceivingItem::with(['receiving.receivable'])->get();

        foreach ($receivingItems as $item) {
            if ($item->quantity_accepted <= 0) {
                continue;
            }

            $receiving = $item->receiving;
            $source    = $receiving->receivable; // Ini adalah PO, Transfer, atau Restock
            
            // 2. Resolve Warehouse ID dan Code secara dinamis
            $warehouseId = null;
            $warehouseCode = 'WH';

            if ($receiving->receivable_type === 'transfer') {
                $warehouseId = $source->to_warehouse_id;
                $warehouseCode = $source->toWarehouse->warehouse_code ?? 'WH';
            } else {
                $warehouseId = $source->warehouse_id;
                $warehouseCode = $source->warehouse->warehouse_code ?? 'WH';
            }

            // Dapatkan unit price dari item sumber (PO/Transfer/Restock)
            // Jika model sumber memiliki relasi ke item-nya, kita akses dari sana
            $sourceItem = $source->items()->where('product_id', $item->product_id)->first();

            $batch = Batch::create([
                'id' => (string) Str::ulid(),
                'batch_code' => 'BCH-' . $warehouseCode . '-' . strtoupper(Str::random(6)),
                'receiving_id' => $receiving->id,
                'product_id' => $item->product_id,
                'warehouse_id' => $warehouseId,
                'initial_quantity' => $item->quantity_accepted,
                'current_quantity' => $item->quantity_accepted,
                'production_date' => $item->production_date ?? now()->subMonth(),
                'expired_date' => $item->expired_date ?? now()->addYear(),
                'price' => $sourceItem?->unit_price ?? 0,
                'condition' => $item->condition ?? 'GOOD',
                'barcode' => null,
            ]);

            $batchService->generateBarcode($batch);

            StockMutations::create([
                'id' => (string) Str::ulid(),
                'warehouse_id' => $warehouseId,
                'batch_id' => $batch->id,
                'change_quantity' => $item->quantity_accepted,
                'before_quantity' => 0,
                'after_quantity' => $item->quantity_accepted,
                'reference_type' => 'RECEIVE',
                'reference_id' => $receiving->id,
                // Menggunakan ID dokumen sumber untuk notes
                'notes' => "Received product to Warehouse {$warehouseCode}", 
                'status' => 'SUCCESS',
            ]);
        }

        $this->seedExtraBatches($batchService);
    }

    private function seedExtraBatches(BatchService $batchService): void
    {
        // ... (Kode seedExtraBatches Anda sudah bagus, tidak perlu diubah) ...
        $warehouses = \App\Models\Warehouse::all();
        $products = \App\Models\Product::all();
        $dummyReceiving = ProductReceiving::first();

        if ($warehouses->isEmpty() || $products->isEmpty() || !$dummyReceiving) return;

        $qtyVariants = [5, 10, 50, 100, 200, 300, 500];

        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                if (Batch::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->exists()) continue;

                $qty = $qtyVariants[array_rand($qtyVariants)];

                $existsInReceiving = \Illuminate\Support\Facades\DB::table('product_receiving_items')
                    ->where('receiving_id', $dummyReceiving->id)
                    ->where('product_id', $product->id)
                    ->exists();

                if (!$existsInReceiving) {
                    \Illuminate\Support\Facades\DB::table('product_receiving_items')->insert([
                        'id' => (string) Str::ulid(),
                        'receiving_id' => $dummyReceiving->id,
                        'product_id' => $product->id,
                        'quantity_accepted' => $qty * 2,
                        'quantity_rejected' => 0,
                        'notes' => 'Dummy item for extra batches',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $batch = Batch::create([
                    'id' => (string) Str::ulid(),
                    'batch_code' => 'BCH-' . $warehouse->warehouse_code . '-' . strtoupper(Str::random(6)),
                    'receiving_id' => $dummyReceiving->id,
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'initial_quantity' => $qty,
                    'current_quantity' => $qty,
                    'production_date' => now()->subMonths(rand(1, 6)),
                    'expired_date' => now()->addMonths(rand(6, 24)),
                    'price' => rand(5000, 100000),
                    'condition' => 'GOOD',
                    'barcode' => null,
                ]);

                $batchService->generateBarcode($batch);
                // ... (sisanya tetap sama)
            }
        }
    }
}