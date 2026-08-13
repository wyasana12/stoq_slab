<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Store;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stores = [
            [
                'store_code' => 'Test1',
                'name' => 'PT Store A',
                'contact_person' => 'Andi Pratama',
                'phone_number' => '+628111111111',
                'email' => 'storeA@gmail.com',
                'street' => 'Jl. Teuku Umar No. 12',
                'postal_code' => '23751',
                'status' => true,
            ],
            [
                'store_code' => 'Test2',
                'name' => 'PT Store B',
                'contact_person' => 'Budi Santoso',
                'phone_number' => '+628222222222',
                'email' => 'storeB@gmail.com',
                'street' => 'Jl. Sudirman No. 45',
                'postal_code' => '23751',
                'status' => true,
            ],
            [
                'store_code' => 'Test3',
                'name' => 'PT Store C',
                'contact_person' => 'Citra Lestari',
                'phone_number' => '+628333333333',
                'email' => 'storeC@gmail.com',
                'street' => 'Jl. Diponegoro No. 8',
                'postal_code' => '23751',
                'status' => false,
            ],
        ];

        foreach ($stores as $w) {
            $region = Region::inRandomOrder()->first();
            $warehouse = Warehouse::inRandomOrder()->first();

            Store::create([
                ...$w,
                'region_id' => $region->id,
                'warehouse_id' => $warehouse->id,
            ]);
        }
    }
}
