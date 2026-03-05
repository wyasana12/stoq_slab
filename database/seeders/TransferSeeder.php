<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\StockMutations;
use App\Models\StockTransfers;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Illuminate\Support\now;

class TransferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $batch = Batch::where('current_quantity', '>', 5)->first();
        $user = User::first();

        if ($batch) {
            $fromWarehouse = Warehouse::find($batch->warehouse_id);
            $toWarehouse = Warehouse::where('id', '!=', $fromWarehouse->id)->first();
        }
        
        if ($batch && $toWarehouse) {
            $quantityTransfer = rand(1, 10);

            $transfer = StockTransfers::create([
                'transfer_code' => 'TF-' . now()->format("Ymd") . "-" . rand(0001, 9999),
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'requested_by' => $user->id,
                'confirmed_by' => $user->id,
                'notes' => null,
                'status' => 'COMPLETED',
            ]);

            DB::table('stock_transfer_items')->insert([
                'id' => (string) Str::ulid(),
                'transfer_id' => $transfer->id,
                'batch_id' => $batch->id,
                'requested_quantity' => $quantityTransfer,
                'approved_quantity' => $quantityTransfer,
            ]);

            $before = $batch->current_quantity;
            $batch->decrement('current_quantity', $quantityTransfer);

            StockMutations::create([
                'warehouse_id' => $fromWarehouse->id,
                'batch_id' => $batch->id,
                'change_quantity' => $quantityTransfer,
                'before_quantity' => $before,
                'after_quantity' => $batch->current_quantity,
                'reference_type' => 'TRANSFER',
                'reference_id' => $transfer->id,
                'notes' => 'Kirim Ke ' . $toWarehouse->name,
                'status' => 'SUCCESS',
            ]);

            $batchDestination = Batch::create([
                'batch_code' => 'BTCH-' . now()->format('Ymd') . '-' . rand(0001, 9999),
                'product_id' => $batch->product_id,
                'warehouse_id' => $toWarehouse->id,
                'supplier_id' => $batch->supplier_id,
                'rack_location' => rand(10, 20),
                'production_date' => $batch->production_date,
                'expired_date' => $batch->expired_date,
                'initial_quantity' => $quantityTransfer,
                'current_quantity' => $quantityTransfer,
                'price' => $batch->price,
                'condition' => $batch->condition,
                'barcode' => $batch->barcode
            ]);

            StockMutations::create([
                'warehouse_id' => $batchDestination->warehouse_id,
                'batch_id' => $batchDestination->id,
                'change_quantity' => $quantityTransfer,
                'before_quantity' => 0,
                'after_quantity' => $quantityTransfer,
                'reference_type' => 'TRANSFER',
                'reference_id' => $transfer->id,
                'notes' => 'Terima Transfer Dari ' . $fromWarehouse->name,
                'status' => 'SUCCESS'
            ]);
        }
    }
}
