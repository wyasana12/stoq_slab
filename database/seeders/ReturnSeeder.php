<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\StockMutation;
use App\Models\StockMutations;
use App\Models\StockReturns;
use App\Models\User;
use Illuminate\Database\Seeder;

use function Illuminate\Support\now;

class ReturnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $batch = Batch::where('current_quantity', '>', 10)->first();
        $user = User::whereNotNull('warehouse_id')->first();

        if ($batch) {
            $quantityReturn = rand(1, 9);

            $return = StockReturns::create([
                'return_code' => 'RT-' . now()->format('Ymd') . '-' . rand(0001, 9999),
                'warehouse_id' => $batch->warehouse_id,
                'batch_id' => $batch->id,
                'requested_quantity' => $quantityReturn,
                'approved_quantity' => $quantityReturn,
                'requested_by' => $user->id,
                'confirmed_by' => $user->id,
                'notes' => null,
                'status' => 'SUCCESS',
            ]);

            $before = $batch->current_quantity;
            $batch->decrement('current_quantity', $quantityReturn);

            StockMutations::create([
                'warehouse_id' => $batch->warehouse_id,
                'batch_id' => $batch->id,
                'change_quantity' => $quantityReturn,
                'before_quantity' => $before,
                'after_quantity' => $batch->current_quantity,
                'reference_type' => 'RETURN',
                'reference_id' => $return->id,
                'notes' => 'Return Barang Kode ' . $return->return_code,
                'status' => 'SUCCESS',
            ]);
        }
    }
}
