<?php

namespace Database\Seeders;

use App\Models\ProductSupplierItem;
use App\Models\Product;
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
        // 1. Ambil data secara acak
        $products = Product::inRandomOrder()->take(2)->get();
        $suppliers = Supplier::inRandomOrder()->take(3)->get();

        // 2. Validasi jumlah data
        if ($products->count() < 2 || $suppliers->count() < 3) {
            $this->command->error('Gagal: Pastikan Anda sudah memiliki minimal 2 produk dan 3 supplier di database sebelum menjalankan seeder ini.');
            return;
        }

        $productId1 = $products[0]->id;
        $productId2 = $products[1]->id;

        $supplierId1 = $suppliers[0]->id;
        $supplierId2 = $suppliers[1]->id;
        $supplierId3 = $suppliers[2]->id;

        // 3. Bungkus dalam 1 transaksi (tidak perlu nested transaction)
        DB::transaction(function () use ($productId1, $productId2, $supplierId1, $supplierId2, $supplierId3) {
            
            // Gunakan updateOrCreate untuk menghindari error unique constraint
            ProductSupplierItem::updateOrCreate(
                [
                    'product_id' => $productId1,
                    'supplier_id' => $supplierId1,
                ],
                [
                    'unit_price' => 120000.00,
                    'min_order_quantity' => 10,
                    'lead_time_days' => 3,
                    'is_preferred' => false,
                ]
            );

            ProductSupplierItem::updateOrCreate(
                [
                    'product_id' => $productId1,
                    'supplier_id' => $supplierId2,
                ],
                [
                    'unit_price' => 115000.00,
                    'min_order_quantity' => 50,
                    'lead_time_days' => 5,
                    'is_preferred' => true,
                ]
            );

            // Simulasi update is_preferred (otomatis memicu event booted di Model)
            $supplierA = ProductSupplierItem::where('product_id', $productId1)
                ->where('supplier_id', $supplierId1)
                ->first();

            if ($supplierA) {
                // Gunakan update() ketimbang save() agar lebih konsisten dan bersih
                $supplierA->update(['is_preferred' => true]);
            }

            ProductSupplierItem::updateOrCreate(
                [
                    'product_id' => $productId2,
                    'supplier_id' => $supplierId3,
                ],
                [
                    'unit_price' => 15000.00,
                    'min_order_quantity' => 100,
                    'lead_time_days' => 2,
                    'is_preferred' => true,
                ]
            );
        });

        // Pindahkan notifikasi keluar dari transaksi
        $this->command->info('Seeder ProductSupplierItem berhasil dijalankan secara aman!');
    }
}