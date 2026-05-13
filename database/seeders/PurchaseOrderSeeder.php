<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Models\ProductSupplierItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem; // Pastikan Model ini di-import
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

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
        $productSupplierItems = ProductSupplierItem::all();

        // Validasi: Pastikan data master sudah ada sebelum menjalankan seeder PO
        if ($suppliers->isEmpty() || $warehouses->isEmpty() || $users->isEmpty() || $productSupplierItems->isEmpty()) {
            $this->command->error('Data master (Supplier, Warehouse, User, atau ProductSupplier) belum lengkap. Silakan jalankan seeder master terlebih dahulu.');
            return;
        }

        $statuses = PurchaseOrderStatus::cases();

        foreach ($statuses as $status) {
            for ($i = 1; $i <= 3; $i++) {
                $warehouse = $warehouses->random();
                $supplier = $suppliers->random();
                $user = $users->random();

                // Filter barang yang hanya disuplai oleh supplier terpilih
                $availableItems = $productSupplierItems->where('supplier_id', $supplier->id);

                // Jika supplier ini belum punya produk, lewati dan cari kombinasi lain
                if ($availableItems->isEmpty()) {
                    continue; 
                }

                // Buat Induk Purchase Order
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
                
                // Ambil jumlah acak (maksimal 4 barang), tapi jangan melebihi stok jenis barang milik supplier
                $randItemsCount = min(rand(1, 4), $availableItems->count());
                $randProductSuppliers = $availableItems->random($randItemsCount);

                foreach ($randProductSuppliers as $psItem) {
                    $qty = rand(30, 50);
                    $price = $psItem->unit_price ?? rand(10000, 50000); // Ambil harga asli dari tabel perantara jika ada
                    $subtotal = $qty * $price;

                    // Menggunakan ELOQUENT alih-alih DB::table()->insert()
                    // ULID dan Timestamps akan digenerate otomatis oleh Model
                    PurchaseOrderItem::create([
                        'purchase_id' => $po->id,
                        'product_supplier_id' => $psItem->id,
                        'quantity_ordered' => $qty,
                        'quantity_received' => ($status->name === 'APPROVED') ? $qty : 0,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                    ]);

                    $totalAmount += $subtotal;
                }

                // Update total amount PO menggunakan method update()
                $po->update(['total_amount' => $totalAmount]); 
            }
        }
        
        $this->command->info('Seeder Purchase Order berhasil dijalankan!');
    }
}