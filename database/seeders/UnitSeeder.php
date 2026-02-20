<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            [
                'name' => 'Lusin',
                'symbol' => null,
            ],
            [
                'name' => 'Kilogram',
                'symbol' => 'Kg',
            ],
            [
                'name' => 'Kardus',
                'symbol' => 'Box',
            ],
            [
                'name' => 'Pieces',
                'symbol' => 'Pcs',
            ],
            [
                'name' => 'Liter',
                'symbol' => 'L',
            ],
        ];

        foreach ($units as $u) {
            Unit::create($u);
        }
    }
}
