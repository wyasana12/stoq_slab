<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\StockMutations;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

use function Illuminate\Support\now;

class BatchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $receivingItems = DB::table('product_receiving_items')
            ->join('product_receivings', 'product_receiving_items.receiving_id', '=', 'product_receivings.id')
            ->join('purchase_orders', 'product_receivings.purchase_id', '=', 'purchase_orders.id')
            ->join('purchase_order_items', function ($join) {
                $join->on('purchase_orders.id', '=', 'purchase_order_items.purchase_id')
                    ->on('product_receiving_items.product_id', '=', 'purchase_order_items.product_id');
            })
            ->select(
                'product_receiving_items.*',
                'product_receivings.warehouse_id',
                'purchase_orders.supplier_id',
                'purchase_order_items.unit_price'
            )
            ->get();
            
        foreach ($receivingItems as $item) {
            $receiving = $item->receiving;
            $purchaseOrder = $receiving->purchaseOrder;

            if ($item->quantity_accepted > 0) {
                $batch = Batch::create([
                    'batch_code' => "BTCH-" . now()->format("Ymd") . "-" . rand(0001, 9999),
                    'product_id' => $item->product_id,
                    'warehouse_id' => $receiving->warehouse_id,
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'receiving_id' => $item->receiving_id,
                    'rack_location' => (string) rand(00, 99),
                    'production_date' => now()->subMonths(2),
                    'expired_date' => now()->addYears(1),
                    'initial_quantity' => $item->quantity_accepeted,
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
                    'notes' => 'Stock Masuk Dari ' . $purchaseOrder->supplier_id,
                    'status' => 'SUCCESS',
                ]);
            }
        }
    }
}
