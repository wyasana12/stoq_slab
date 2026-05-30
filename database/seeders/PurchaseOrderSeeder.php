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

class PurchaseOrderSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = Supplier::all();
        $warehouses = Warehouse::all();
        $users = User::whereNotNull('warehouse_id')->get();
        $productSupplierItems = ProductSupplierItem::all();

        foreach ($warehouses as $warehouse) {

            $warehouseUsers = $users->where('warehouse_id', $warehouse->id);

            if ($warehouseUsers->isEmpty()) {
                continue;
            }

            for ($i = 1; $i <= 3; $i++) {

                $supplier = $suppliers->random();
                $user = $warehouseUsers->random();

                $availableItems = $productSupplierItems
                    ->where('supplier_id', $supplier->id)
                    ->unique('product_id')
                    ->values();

                if ($availableItems->isEmpty()) {
                    continue;
                }

                $po = PurchaseOrder::create([
                    'po_code' => 'PO-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                    'supplier_id' => $supplier->id,
                    'warehouse_id' => $warehouse->id,
                    'created_by' => $user->id,
                    'total_amount' => 0,
                    'status' => PurchaseOrderStatus::APPROVED,
                    'order_date' => now()->subDays(rand(1, 10)),
                    'approved_at' => now(),
                    'notes' => null,
                ]);

                $totalAmount = 0;

                $items = $availableItems
                    ->shuffle()
                    ->take(min(rand(1, 4), $availableItems->count()));

                foreach ($items as $psItem) {

                    $qty = rand(
                        $psItem->min_order_quantity ?? 1,
                        ($psItem->min_order_quantity ?? 1) + 20
                    );

                    $price = $psItem->unit_price;
                    $subtotal = $qty * $price;

                    PurchaseOrderItem::create([
                        'purchase_id' => $po->id,
                        'product_id' => $psItem->product_id,
                        'quantity_ordered' => $qty,
                        'quantity_received' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                    ]);

                    $totalAmount += $subtotal;
                }

                $po->update([
                    'total_amount' => $totalAmount
                ]);
            }
        }
    }
}
