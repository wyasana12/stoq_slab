<?php

namespace Database\Seeders;

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
                'name' => 'Gudang A',
                'location' => 'Lokasi A',
                'phone_number' => '62857-1111-1111',
            ],
            [
                'name' => 'Gudang B',
                'location' => 'Lokasi B',
                'phone_number' => '62878-2222-2222',
            ],
            [
                'name' => 'Gudang C',
                'location' => 'Lokasi C',
                'phone_number' => '62876-3333-3333'
            ],
        ];

        foreach ($warehouses as $w) {
            Warehouse::create($w);
        }
    }
}
