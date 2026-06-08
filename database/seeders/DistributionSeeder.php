<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\StockDistributions;
use App\Models\StockMutations;
use App\Models\User;
use App\Enums\MutationStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Illuminate\Support\now;

class DistributionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $batches = Batch::where('current_quantity', '>=', 2)->limit(5)->get();
        $users = User::whereNotNull('warehouse_id')->first();

        if (! $users) {
            return;
        }

        foreach ($batches as $batch) {
            $quantityOut = min(rand(2, 5), $batch->current_quantity);

            if ($quantityOut <= 0) {
                continue;
            }

            $dist = StockDistributions::create([
                'distribution_code' => "DIST-" . now()->format('Ymd') . "-" . rand(0001, 9999),
                'warehouse_id' => $batch->warehouse_id,
                'dispatched_at' => now(),
                'requested_by' => $users->id,
                'confirmed_by' => $users->id,
                'notes' => null,
                'status' => 'COMPLETED',
            ]);

            DB::table('stock_distribution_items')->insert([
                'id' => (string) Str::ulid(),
                'distribution_id' => $dist->id,
                'batch_id' => $batch->id,
                'requested_quantity' => $quantityOut,
                'approved_quantity' => $quantityOut,
            ]);

            $before = $batch->current_quantity;
            $batch->decrement('current_quantity', $quantityOut);

            StockMutations::create([
                'warehouse_id' => $batch->warehouse_id,
                'batch_id' => $batch->id,
                'change_quantity' => $quantityOut,
                'before_quantity' => $before,
                'after_quantity' => $batch->current_quantity,
                'reference_type' => 'DISTRIBUTION',
                'reference_id' => $dist->id,
                'notes' => 'Barang masuk ke ' . ($dist->store?->name ?? 'toko'),
                'status' => MutationStatus::DISTRIBUTION_COMPLETED->value,
            ]);
        }
    }
}
