<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\RackLocation;
use App\Models\RackWarehouse;
use Illuminate\Support\Str;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class RackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $racks = [
            [
                'rack_code' => 'RA-0001',
                'levels' => 3,
                'bins_per_level' => 4,
            ],
            [
                'rack_code' => 'RA-0002',
                'levels' => 2,
                'bins_per_level' => 3
            ],
        ];

        $warehouse = Warehouse::first()?->id;

        foreach ($racks as $i) {
            $rack = RackWarehouse::create([
                'id' => (string) Str::ulid(),
                'rack_code' => $i['rack_code'],
                'warehouse_id' => $warehouse,
                'status' => 'AVAILABLE'
            ]);

            $this->generateLocations(
                $rack,
                $i['levels'],
                $i['bins_per_level']
            );
        }
    }

private function generateLocations(RackWarehouse $rack, int $levels, int $binsPerLevel): void
    {
        for ($level = 1; $level <= $levels; $level++) {
            for ($bin = 1; $bin <= $binsPerLevel; $bin++) {

                RackLocation::create([
                    'id' => (string) Str::ulid(),
                    'rack_id' => $rack->id,
                    'level' => $level,
                    'bin' => $bin,
                    'location_code' => sprintf(
                        '%s-L%02d-B%02d',
                        $rack->rack_code,
                        $level,
                        $bin
                    ),
                    'capacity_unit' => 'CARTON',
                    'capacity' => 100,
                    'used' => 0,
                    'status' => 'AVAILABLE',
                ]);
            }
        }
    }
}
