<?php

namespace Database\Seeders;

use App\Models\ProductReceivingItem;
use App\Models\ProductReceiving;
use App\Models\Batch;
use App\Models\StockMutations;
use App\Services\BatchService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class BatchSeeder extends Seeder
{
    public function run(BatchService $batchService): void
    {
        $receivingItems = ProductReceivingItem::with(['receiving.receivable'])->get();

        foreach ($receivingItems as $item) {
            if ($item->quantity_accepted <= 0) {
                continue;
            }

            $receiving = $item->receiving;
            $source    = $receiving->receivable;
            
            $warehouseId = null;
            $warehouseCode = 'WH';

            if ($receiving->receivable_type === 'transfer') {
                $warehouseId = $source->to_warehouse_id;
                $warehouseCode = $source->toWarehouse->warehouse_code ?? 'WH';
            } else {
                $warehouseId = $source->warehouse_id;
                $warehouseCode = $source->warehouse->warehouse_code ?? 'WH';
            }

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
                'condition' => 'BAIK', 
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
                'notes' => "Received product to Warehouse {$warehouseCode}", 
                'status' => 'SUCCESS',
            ]);
        }

        $this->seedExtraBatches($batchService);
    }

    private function seedExtraBatches(BatchService $batchService): void
    {
        $warehouses = \App\Models\Warehouse::all();
        $products = \App\Models\Product::all();
        $dummyReceiving = ProductReceiving::first();

        if ($warehouses->isEmpty() || $products->isEmpty() || !$dummyReceiving) return;

        $qtyVariants = [5, 10, 50, 100, 200, 300, 500];

        foreach ($warehouses as $warehouse) {
            foreach ($products as $product) {
                if (Batch::where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->exists()) continue;

                $qty = $qtyVariants[array_rand($qtyVariants)];

                $existsInReceiving = DB::table('product_receiving_items')
                    ->where('receiving_id', $dummyReceiving->id)
                    ->where('product_id', $product->id)
                    ->exists();

                if (!$existsInReceiving) {
                    DB::table('product_receiving_items')->insert([
                        'id' => (string) Str::ulid(),
                        'receiving_id' => $dummyReceiving->id,
                        'product_id' => $product->id,
                        'quantity_accepted' => $qty * 2,
                        'quantity_rejected' => 0,
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
                    'condition' => 'BAIK', 
                    'barcode' => null,
                ]);

                $batchService->generateBarcode($batch);

                StockMutations::create([
                    'id' => (string) Str::ulid(),
                    'warehouse_id' => $warehouse->id,
                    'batch_id' => $batch->id,
                    'change_quantity' => $qty,
                    'before_quantity' => 0,
                    'after_quantity' => $qty,
                    'reference_type' => 'RECEIVE',
                    'reference_id' => $dummyReceiving->id,
                    'notes' => "Received dummy product to Warehouse {$warehouse->warehouse_code}", 
                    'status' => 'SUCCESS',
                ]);
            }
        }
    }
}