<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductSupplierItem;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ambil 2 produk dan 3 supplier secara acak
        $products = Product::inRandomOrder()->take(2)->get();
        $suppliers = Supplier::inRandomOrder()->take(3)->get();

        // Validasi data
        if ($products->count() < 2 || $suppliers->count() < 3) {
            $this->command->error(
                'Seeder gagal. Pastikan terdapat minimal 2 produk dan 3 supplier.'
            );
            return;
        }

        $product1 = $products[0];
        $product2 = $products[1];

        $supplier1 = $suppliers[0];
        $supplier2 = $suppliers[1];
        $supplier3 = $suppliers[2];

        /**
         * Generator SPN
         * Format:
         * SPN-{SUPPLIER_CODE}-{SKU}
         */
        $makeSpn = fn (Supplier $supplier, Product $product): string =>
            sprintf(
                'SPN-%s-%s',
                strtoupper(trim($supplier->supplier_code)),
                strtoupper(trim($product->sku))
            );

        DB::transaction(function () use (
            $product1,
            $product2,
            $supplier1,
            $supplier2,
            $supplier3,
            $makeSpn
        ) {

            ProductSupplierItem::updateOrCreate(
                [
                    'product_id' => $product1->id,
                    'supplier_id' => $supplier1->id,
                ],
                [
                    'SPN' => $makeSpn($supplier1, $product1),
                    'unit_price' => 120000.00,
                    'min_order_quantity' => 10,
                    'lead_time_days' => 3,
                    'return_limit_days' => 7,
                    'is_preferred' => false,
                ]
            );

            ProductSupplierItem::updateOrCreate(
                [
                    'product_id' => $product1->id,
                    'supplier_id' => $supplier2->id,
                ],
                [
                    'SPN' => $makeSpn($supplier2, $product1),
                    'unit_price' => 115000.00,
                    'min_order_quantity' => 50,
                    'lead_time_days' => 5,
                    'return_limit_days' => 14,
                    'is_preferred' => true,
                ]
            );

            // Simulasi perubahan preferred supplier
            ProductSupplierItem::where('product_id', $product1->id)
                ->where('supplier_id', $supplier1->id)
                ->update([
                    'is_preferred' => true,
                ]);

            ProductSupplierItem::updateOrCreate(
                [
                    'product_id' => $product2->id,
                    'supplier_id' => $supplier3->id,
                ],
                [
                    'SPN' => $makeSpn($supplier3, $product2),
                    'unit_price' => 15000.00,
                    'min_order_quantity' => 100,
                    'lead_time_days' => 2,
                    'return_limit_days' => 30,
                    'is_preferred' => true,
                ]
            );
        });

        $this->command->info('ProductSupplierSeeder berhasil dijalankan.');
    }
}