<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'PT Supplier A',
                'location' => 'Jakarta Barat',
                'phone_number' => '0271-1111-1111',
                'email' => 'supplierA@gmail.com'
            ],
            [
                'name' => 'PT Supplier B',
                'location' => 'Jakarta Utara',
                'phone_number' => '0271-2222-2222',
                'email' => 'supplierB@gmail.com'
            ],
            [
                'name' => 'PT Supplier C',
                'location' => 'Jakarta Selatan',
                'phone_number' => '0271-3333-3333',
                'email' => 'supplierC@gmail.com'
            ],
        ];

        foreach ($suppliers as $s) {
            Supplier::create($s);
        }
    }
}
