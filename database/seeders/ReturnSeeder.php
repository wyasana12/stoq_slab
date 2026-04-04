<?php

namespace Database\Seeders;

use App\Enums\ReturnStatus;
use App\Models\Batch;
use App\Models\StockReturns;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

use function Illuminate\Support\now;

class ReturnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $batch = Batch::query()
            ->where('current_quantity', '>', 10)
            ->first();

        if (! $batch) {
            $this->command?->warn('ReturnSeeder skip: batch dengan current_quantity > 10 tidak ditemukan.');
            return;
        }

        $warehouse = Warehouse::query()->find($batch->warehouse_id);

        if (! $warehouse) {
            $this->command?->warn('ReturnSeeder skip: warehouse dari batch tidak ditemukan.');
            return;
        }

        $user = User::query()
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if (! $user) {
            $this->command?->warn('ReturnSeeder skip: user dengan warehouse_id yang sama tidak ditemukan.');
            return;
        }

        $requestedQty = rand(1, 9);

        $stockReturn = StockReturns::create([
            'return_code' => 'RT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
            'warehouse_id' => $warehouse->id,
            'batch_id' => $batch->id,
            'requested_quantity' => $requestedQty,
            'approved_quantity' => 0,
            'reason' => 'damaged',
            'requested_by' => $user->id,
            'confirmed_by' => null,
            'notes' => 'Seeder sample return for CRUD test',
            'status' => ReturnStatus::REQUESTED->value,
        ]);

        $this->command?->info('ReturnSeeder success. return_id=' . $stockReturn->id);
    }
}
