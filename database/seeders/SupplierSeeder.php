<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Region;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'PT Supplier A',
                'contact_person' => 'Andi Pratama',
                'phone_number' => '+628111111111',
                'email' => 'supplierA@gmail.com',
                'street' => 'Jl. Teuku Umar No. 12',
                'postal_code' => '23751',
                'status' => true,
            ],
            [
                'name' => 'PT Supplier B',
                'contact_person' => 'Budi Santoso',
                'phone_number' => '+628222222222',
                'email' => 'supplierB@gmail.com',
                'street' => 'Jl. Sudirman No. 45',
                'postal_code' => '23751',
                'status' => true,
            ],
            [
                'name' => 'PT Supplier C',
                'contact_person' => 'Citra Lestari',
                'phone_number' => '+628333333333',
                'email' => 'supplierC@gmail.com',
                'street' => 'Jl. Diponegoro No. 8',
                'postal_code' => '23751',
                'status' => false,
            ],
        ];

        foreach ($suppliers as $s) {
            $region = Region::inRandomOrder()->first();
            $category = Category::inRandomOrder()->first();

            Supplier::create([
                ...$s,
                'category_id' => $category->id,
                'region_id' => $region->id,
            ]);
        }
    }
}