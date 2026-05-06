<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\ProductReceivingItem;
use App\Models\StockMutations;
use Illuminate\Database\Seeder;

use function Illuminate\Support\now;

class BatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $receivingItems = ProductReceivingItem::with(['receiving.purchase'])->get();
            
        foreach ($receivingItems as $item) {
            $receiving = $item->receiving;
            $purchase = $receiving->purchase;

            if ($item->quantity_accepted > 0) {
                $batch = Batch::create([
                    'batch_code' => "BTCH-" . now()->format("Ymd") . "-" . rand(0001, 9999),
                    'product_id' => $item->product_id,
                    'warehouse_id' => $purchase->warehouse_id,
                    'receiving_id' => $item->receiving_id,
                    'rack_location' => (string) rand(00, 99),
                    'production_date' => now()->subMonths(2),
                    'expired_date' => now()->addYears(1),
                    'initial_quantity' => $item->quantity_accepted,
                    'current_quantity' => $item->quantity_accepted,
                    'price' => $item->unit_price + 1500,
                    'condition' => null,
                    'barcode' => rand(10000000, 999999999),
                ]);

                StockMutations::create([
                    'warehouse_id' => $batch->warehouse_id,
                    'batch_id' => $batch->id,
                    'change_quantity' => $item->quantity_accepted,
                    'before_quantity' => 0,
                    'after_quantity' => $item->quantity_accepted,
                    'reference_type' => 'RECEIVE',
                    'reference_id' => $item->receiving_id,
                    'notes' => 'Stock Masuk Dari ' . $purchase->supplier_id,
                    'status' => 'SUCCESS',
                ]);
            }
        }
    }
}
