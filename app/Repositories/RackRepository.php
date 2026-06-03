<?php

namespace App\Repositories;

use App\Models\RackLocation;
use App\Models\RackWarehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RackRepository
{
    public function getAllPaginated(int $perPage = 10)
    {
        $userId = Auth::user()->warehouse_id;

        $query = RackWarehouse::with(['warehouse:id,name'])->select(['id', 'warehouse_id', 'rack_code', 'status']);

        return $query->where('warehouse_id', $userId)->latest()->paginate($perPage);
    }

    public function getById(RackWarehouse $rackWarehouse): RackWarehouse
    {
        return $rackWarehouse->load(['warehouse', 'locations']);
    }

    public function create(array $data): RackWarehouse
    {
        return RackWarehouse::create($data);
    }

    public function assignLocation(RackWarehouse $rack, int $levels, int $bins_per_level, string $capacity_unit, int $capacity): void
    {
        $data = [];

        for ($level = 1; $level <= $levels; $level++) {
            for ($bin = 1; $bin <= $bins_per_level; $bin++) {
                $data[] = [
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
                    'capacity_unit' => $capacity_unit,
                    'capacity' => $capacity,
                    'used' => 0,
                    'status' => 'AVAILABLE',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        RackLocation::insert($data);
    }

    public function update(RackLocation $location, array $data)
    {
        return $location->update($data);
    }

    public function deleteLocation(RackWarehouse $rack): void
    {
        $rack->locations()->delete();
    }

    public function deleteRack(RackWarehouse $rack) : void {
        $rack->delete();
    }
}
