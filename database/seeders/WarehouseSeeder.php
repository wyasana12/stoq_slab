<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = [
            [
                'name' => 'PT Warehouse A',
                'contact_person' => 'Andi Pratama',
                'phone_number' => '+628111111111',
                'email' => 'warehouseA@gmail.com',
                'street' => 'Jl. Teuku Umar No. 12',
                'postal_code' => '23751',
                'status' => true,
            ],
            [
                'name' => 'PT Warehouse B',
                'contact_person' => 'Budi Santoso',
                'phone_number' => '+628222222222',
                'email' => 'warehouseB@gmail.com',
                'street' => 'Jl. Sudirman No. 45',
                'postal_code' => '23751',
                'status' => true,
            ],
            [
                'name' => 'PT Warehouse C',
                'contact_person' => 'Citra Lestari',
                'phone_number' => '+628333333333',
                'email' => 'warehouseC@gmail.com',
                'street' => 'Jl. Diponegoro No. 8',
                'postal_code' => '23751',
                'status' => false,
            ],
        ];

        foreach ($warehouses as $w) {
            $region = Region::inRandomOrder()->first();

            Warehouse::create([
                ...$w,
                'region_id' => $region->id,
            ]);
        }
    }
}
