<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Illuminate\Support\now;

class PurchaseOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = Supplier::all();
        $warehouses = Warehouse::all();
        $users = User::whereNotNull('warehouse_id')->get();
        $products = Product::all();

        $statuses = PurchaseOrderStatus::cases();

        foreach ($statuses as $s) {
            for ($i = 1; $i <= 3; $i++) {
                $warehouse = $warehouses->random();
                $supplier = $suppliers->random();
                $user = $users->random();

                $po = PurchaseOrder::create([
                    'po_code' => "PO-" . now()->format("Ymd") . "-" . rand(0001, 9999),
                    'supplier_id' => $supplier->id,
                    'warehouse_id' => $warehouse->id,
                    'created_by' => $user->id,
                    'total_amount' => 0,
                    'status' => $s,
                    'order_date' => now()->subDays(rand(1, 10)),
                    'approved_at' => ($s === 'APPROVED') ? now() : null,
                    'notes' => null,
                ]);

                $totalAmount = 0;
                $randProduct = $products->random(rand(2, 4));

                foreach ($randProduct as $r) {
                    $qty = rand(30, 50);
                    $price = rand(10000, 50000);
                    $subtotal = $qty * $price;

                    DB::table('purchase_order_items')->insert([
                        'id' => (string) Str::ulid(),
                        'product_id' => $r->id,
                        'purchase_id' => $po->id,
                        'quantity_ordered' => $qty,
                        'quantity_received' => ($s === 'APPROVED') ? $qty : 0,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                    ]);

                    $totalAmount += $subtotal;
                }

                $po->updated(['total_amount' => $totalAmount]);
            }
        }
    }
}
