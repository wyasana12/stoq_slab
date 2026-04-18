<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Restock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Illuminate\Support\now;

class RestockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = Warehouse::first();
        $user = User::where('warehouse_id', $warehouse->id)->first();
        $products = Product::limit(2)->get();

        if ($warehouse && $user) {
            $restock = Restock::create([
                'restock_code' => 'RC-' . now()->format('Ymd') . '-' . rand(0001, 9999),
                'warehouse_id' => $warehouse->id,
                'requested_by' => $user->id,
                'confirmed_by' => $user->id,
                'status' => 'restocked',
                'notes' => null,
            ]);

            foreach ($products as $product) {
                DB::table('restock_items')->insert([
                    'id' => (string) Str::ulid(),
                    'restock_id' => $restock->id,
                    'product_id' => $product->id,
                    'requested_quantity' => rand(20, 50),
                ]);
            }
        }
    }
}
