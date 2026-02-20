<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Minuman'],
            ['name' => 'Makanan Ringan'],
            ['name' => 'Makanan Instant'],
            ['name' => 'Alat Mandi'],
            ['name' => 'Alat Tulis'],
        ];

        foreach ($categories as $a) {
            Category::create($a);
        }
    }
}
