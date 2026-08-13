<?php

namespace Database\Seeders;

use App\Enums\ReturnStatus;
use App\Models\StockReturns;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class ReturnSeeder extends Seeder
{
    public function run(): void
    {
        $receivingItem = DB::table('product_receiving_items')
            ->where('quantity_accepted', '>', 5)
            ->first();

        if (! $receivingItem) {
            $this->command?->warn('ReturnSeeder skip: data product_receiving_items dengan quantity_accepted > 5 tidak ditemukan.');
            return;
        }

        $receiving = DB::table('product_receivings')
            ->where('id', $receivingItem->receiving_id)
            ->first();

        if (! $receiving) {
            $this->command?->warn('ReturnSeeder skip: data induk product_receivings tidak ditemukan.');
            return;
        }

        $warehouse = Warehouse::query()->first();

        if (! $warehouse) {
            $this->command?->warn('ReturnSeeder skip: Tidak ada data warehouse tersedia di database.');
            return;
        }

        $user = User::query()->first();

        if (! $user) {
            $this->command?->warn('ReturnSeeder skip: Tidak ada data user tersedia untuk mengisi requested_by.');
            return;
        }

        $requestedQty = rand(1, min(3, $receivingItem->quantity_accepted));

        $stockReturn = StockReturns::create([
            'return_code' => 'RT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
            'receiving_id' => $receiving->id,
            'product_id' => $receivingItem->product_id,
            'warehouse_id' => $warehouse->id,
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
