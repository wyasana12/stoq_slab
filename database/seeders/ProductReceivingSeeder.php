<?php

namespace Database\Seeders;

use App\Enums\ReceiveStatus;
use App\Models\ProductReceiving;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductReceivingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Gunakan Eager Loading untuk memuat relasi (sesuaikan nama relasi jika berbeda)
        // Asumsinya PO Item memiliki relasi 'productSupplierItem' ke model ProductSupplierItem
        $approvedPO = PurchaseOrder::with(['items'])->where('status', 'approved')->get();

        $statuses = [
            ReceiveStatus::PROCESS,
            ReceiveStatus::PARTIAL,
            ReceiveStatus::FULL,
            ReceiveStatus::REJECT,
        ];
        
        foreach ($approvedPO as $index => $po) {
            $currentStatus = $statuses[$index % count($statuses)];
            
            $staff = User::where('warehouse_id', $po->warehouse_id)->first() ?? User::first();
            if (!$staff) continue; 

            $receiving = ProductReceiving::create([
                'receiving_code' => "RCV-" . now()->format('Ymd') . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'purchase_id' => $po->id,
                'status' => $currentStatus->value,
                'receiving_date' => now(),
                'receiving_by' => $staff->id,
            ]);

            $itemsToInsert = [];

            foreach ($po->items as $item) {
                $qtyAccepted = 0;
                $qtyRejected = 0;

                if ($currentStatus === ReceiveStatus::FULL) {
                    $qtyAccepted = $item->quantity_ordered;
                } elseif ($currentStatus === ReceiveStatus::PARTIAL) {
                    $qtyAccepted = floor($item->quantity_ordered / 2);
                    $qtyRejected = $item->quantity_ordered - $qtyAccepted;
                } elseif ($currentStatus === ReceiveStatus::REJECT) {
                    $qtyRejected = $item->quantity_ordered;
                }

                // AMBIL PRODUCT ID DARI TABEL PRODUCT_SUPPLIER_ITEMS
                // Asumsi 1: Jika menggunakan Eloquent Relationship (contoh: $item->productSupplier->product_id)
                // Asumsi 2: Gunakan DB::table sebagai fallback aman jika relasi belum didefinisikan.
                // NOTE: Ganti 'product_supplier_item_id' dengan nama kolom foreign key yang sebenarnya di tabel PO Items Anda.
                
                $productId = null;
                
                if (isset($item->product_id)) {
                    $productId = $item->product_id; // Fallback jika ternyata ada
                } else {
                    // Cari product_id berdasarkan foreign key yang mengarah ke product_supplier_items
                    $supplierItemId = $item->product_supplier_item_id ?? $item->product_supplier_id; 
                    
                    if ($supplierItemId) {
                        $productId = DB::table('product_supplier_items')
                                        ->where('id', $supplierItemId)
                                        ->value('product_id');
                    }
                }

                // Lewati item ini jika product_id tetap tidak ditemukan (mencegah error 1048 Cannot be null)
                if (!$productId) {
                    continue; 
                }

                $itemsToInsert[] = [
                    'id' => (string) Str::ulid(),
                    'product_id' => $productId, // Menggunakan product_id yang sudah difilter
                    'receiving_id' => $receiving->id,
                    'quantity_accepted' => $qtyAccepted,
                    'quantity_rejected' => $qtyRejected,
                    'notes' => "Status: " . $currentStatus->name,
                    'created_at' => now(), 
                    'updated_at' => now(),
                ];
            }

            if (!empty($itemsToInsert)) {
                DB::table('product_receiving_items')->insert($itemsToInsert);
            }
        }
    }
}