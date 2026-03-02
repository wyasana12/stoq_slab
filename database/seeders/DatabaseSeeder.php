<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            Roleseeder::class,
            RegionImportSeeder::class,
            WarehouseSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            UnitSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            PurchaseOrderSeeder::class,
            ProductReceivingSeeder::class,
            BatchSeeder::class,
            RestockSeeder::class,
            ReturnSeeder::class,
            TransferSeeder::class,
            DistributionSeeder::class,
        ]);
    }
}
