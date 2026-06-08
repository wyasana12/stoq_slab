<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            Roleseeder::class,
            RegionImportSeeder::class,
            WarehouseSeeder::class,
            StoreSeeder::class,
            RackSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            UnitSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            ProductSupplierSeeder::class,
            PurchaseOrderSeeder::class,
            ProductReceivingSeeder::class,
            BatchSeeder::class,
            RestockSeeder::class,
            ReturnSeeder::class,
            TransferSeeder::class,
            DistributionSeeder::class,
            StockMutationSeeder::class,
        ]);
    }
}
