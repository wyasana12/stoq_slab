<?php

namespace Database\Seeders;

use App\Enums\ReceiveStatus;
use App\Models\ProductReceiving;
use App\Models\PurchaseOrder;
use App\Models\StockTransfers;
use App\Models\Restock;
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
        $statuses = [
            ReceiveStatus::PROCESS,
            ReceiveStatus::PARTIAL,
            ReceiveStatus::FULL,
            ReceiveStatus::REJECT,
        ];

        // 1. Kumpulkan semua dokumen yang siap diterima ke dalam satu array seragam
        $documents = [];

        // Ambil data Purchase Order
        foreach (PurchaseOrder::with(['items'])->where('status', 'approved')->get() as $po) {
            $documents[] = [
                'type' => 'purchase_order',
                'model' => $po,
                'warehouse_id' => $po->warehouse_id,
            ];
        }

        // Ambil data Stock Transfers
        foreach (StockTransfers::with(['items'])->where('status', 'COMPLETED')->get() as $tf) {
            $documents[] = [
                'type' => 'transfer',
                'model' => $tf,
                'warehouse_id' => $tf->to_warehouse_id,
            ];
        }

        // Ambil data Restock
        foreach (Restock::with(['items'])->where('status', 'COMPLETED')->get() as $rs) {
            $documents[] = [
                'type' => 'restock',
                'model' => $rs,
                'warehouse_id' => $rs->warehouse_id,
            ];
        }

        // 2. Lakukan perulangan untuk membuat data penerimaan dari semua jenis dokumen
        foreach ($documents as $index => $docData) {
            $type = $docData['type'];
            $model = $docData['model'];
            $warehouseId = $docData['warehouse_id'];

            $currentStatus = $statuses[$index % count($statuses)];
            
            $staff = User::where('warehouse_id', $warehouseId)->first() ?? User::first();
            if (!$staff) continue; 

            // Simpan data induk penerimaan dengan polymorphic keys
            $receiving = ProductReceiving::create([
                'receiving_code' => "RCV-" . now()->format('Ymd') . "-" . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'receivable_type' => $type,
                'receivable_id' => $model->id,
                'status' => $currentStatus->value,
                'receiving_date' => now(),
                'receiving_by' => $staff->id,
            ]);

            $itemsToInsert = [];

            foreach ($model->items as $item) {
                // Dinamis membaca kuantitas yang diharapkan (PO menggunakan quantity_ordered, lainnya quantity)
                $expectedQuantity = $item->quantity_ordered ?? $item->quantity ?? 0;

                if ($expectedQuantity <= 0) continue;

                $qtyAccepted = 0;
                $qtyRejected = 0;

                if ($currentStatus === ReceiveStatus::FULL) {
                    $qtyAccepted = $expectedQuantity;
                } elseif ($currentStatus === ReceiveStatus::PARTIAL) {
                    $qtyAccepted = floor($expectedQuantity / 2);
                    $qtyRejected = $expectedQuantity - $qtyAccepted;
                } elseif ($currentStatus === ReceiveStatus::REJECT) {
                    $qtyRejected = $expectedQuantity;
                }

                $productId = null;
                
                // Fallback pencarian Product ID khusus untuk skema Purchase Order lama
                if (isset($item->product_id)) {
                    $productId = $item->product_id; 
                } elseif ($type === 'purchase_order') {
                    $supplierItemId = $item->product_supplier_item_id ?? $item->product_supplier_id ?? null; 
                    if ($supplierItemId) {
                        $productId = DB::table('product_supplier_items')
                                        ->where('id', $supplierItemId)
                                        ->value('product_id');
                    }
                }

                // Lewati jika produk tidak valid
                if (!$productId) {
                    continue; 
                }

                $itemsToInsert[] = [
                    'id' => (string) Str::ulid(),
                    'product_id' => $productId,
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