<?php

namespace Database\Seeders;

use App\Enums\ReceiveStatus;
use App\Models\ProductReceiving;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Illuminate\Support\now;

class ProductReceivingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $approvedPO = PurchaseOrder::with('items')->where('status', 'approved')->get();

        $statuses = [
            ReceiveStatus::PROCESS,
            ReceiveStatus::PARTIAL,
            ReceiveStatus::FULL,
            ReceiveStatus::REJECT,
        ];
        
        foreach ($approvedPO as $index => $po) {
            $currentStatus = $statuses[$index % count($statuses)];
            $staff = User::where('warehouse_id', $po->warehouse_id)->first();

            $receiving = ProductReceiving::create([
                'receiving_code' => "RCV-".now()->format('Ymd')."-".rand(0001, 9999),
                'purchase_id' => $po->id,
                'status' => $currentStatus->value,
                'receiving_date' => now(),
                'receiving_by' => $staff->id,
            ]);

            foreach ($po->items as $item) {
                $qtyAccepted = 0;
                $qtyRejected = 0;

                if($currentStatus === ReceiveStatus::FULL) {
                    $qtyAccepted = $item->quantity_ordered;
                } elseif ($currentStatus === ReceiveStatus::PARTIAL) {
                    $qtyAccepted = floor($item->quantity_ordered/2);
                    $qtyRejected = $item->quantity_ordered - $qtyAccepted;
                } elseif ($currentStatus === ReceiveStatus::REJECT) {
                    $qtyRejected = $item->quantity_ordered;
                }

                DB::table('product_receiving_items')->insert([
                    'id' => (string) Str::ulid(),
                    'product_id' => $item->product_id,
                    'receiving_id' => $receiving->id,
                    'quantity_accepted' => $qtyAccepted,
                    'quantity_rejected' => $qtyRejected,
                    'notes' => "Status: ".$currentStatus->name,
                ]);
            }
        }
    }
}
