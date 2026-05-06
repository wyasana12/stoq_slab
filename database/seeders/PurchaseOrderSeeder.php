<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Models\ProductSupplierItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::all();
        $warehouses = Warehouse::all();
        $users = User::whereNotNull('warehouse_id')->get();
        $productSupplierItems = ProductSupplierItem::all();

        if ($suppliers->isEmpty() || $warehouses->isEmpty() || $users->isEmpty() || $productSupplierItems->isEmpty()) {
            $this->command->error('Master data is incomplete. Please seed suppliers, warehouses, users, and product-supplier items first.');
            return;
        }

        $statuses = PurchaseOrderStatus::cases();

        foreach ($statuses as $status) {
            for ($i = 1; $i <= 3; $i++) {

                $warehouse = $warehouses->random();
                $supplier = $suppliers->random();
                $user = $users->random();

                $availableItems = $productSupplierItems
                    ->where('supplier_id', $supplier->id)
                    ->unique('product_id')
                    ->values();

                if ($availableItems->isEmpty()) {
                    continue;
                }

                $po = PurchaseOrder::create([
                    'po_code' => "PO-" . now()->format("Ymd") . "-" . rand(1000, 9999),
                    'supplier_id' => $supplier->id,
                    'warehouse_id' => $warehouse->id,
                    'created_by' => $user->id,
                    'total_amount' => 0,
                    'status' => $status,
                    'order_date' => now()->subDays(rand(1, 10)),
                    'approved_at' => ($status->name === 'APPROVED') ? now() : null,
                    'notes' => null,
                ]);

                $totalAmount = 0;

                $randItemsCount = min(rand(1, 4), $availableItems->count());

                $randProductSuppliers = $availableItems->shuffle()->take($randItemsCount);

                foreach ($randProductSuppliers as $psItem) {

                    $qty = rand(
                        $psItem->min_order_quantity ?? 1,
                        ($psItem->min_order_quantity ?? 1) + 20
                    );

                    $price = $psItem->unit_price ?? rand(10000, 50000);
                    $subtotal = $qty * $price;

                    PurchaseOrderItem::create([
                        'id' => Str::ulid(),
                        'purchase_id' => $po->id,
                        'product_id' => $psItem->product_id,
                        'quantity_ordered' => $qty,
                        'quantity_received' => ($status->name === 'APPROVED') ? $qty : 0,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                    ]);

                    $totalAmount += $subtotal;
                }

                $po->update(['total_amount' => $totalAmount]);
            }
        }

        $this->command->info('Purchase Order seeder executed successfully!');
    }
}