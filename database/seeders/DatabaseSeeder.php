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
            UserSeeder::class,
            CategorySeeder::class,
            UnitSeeder::class,
            RackSeeder::class,
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

        // Regenerate DSS cache immediately to match the newly seeded database IDs
        $job = new \App\Jobs\GenerateDssCacheJob();
        $job->handle(
            app(\App\Services\StockAnalysisService::class),
            app(\App\Services\DssRecommendationService::class),
            app(\App\Services\DssCacheService::class)
        );
    }
}
