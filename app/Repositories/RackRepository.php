<?php

namespace App\Repositories;

use App\Models\RackLocation;
use App\Models\RackWarehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RackRepository
{
    public function getAllPaginated(int $perPage = 10, ?string $search = null, ?string $status = null)
    {
        $warehouseId = Auth::user()->warehouse_id;

        $query = RackWarehouse::with(['warehouse:id,name', 'locations'])
            ->where('warehouse_id', $warehouseId);

        if ($search) {
            $query->where('rack_code', 'LIKE', "%{$search}%");
        }

        if ($status) {
            $query->where('status', strtoupper($status));
        }

        return $query->latest()->paginate($perPage);
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

    public function locationById(string $id): RackLocation
    {
        return RackLocation::lockForUpdate()->findOrFail($id);
    }

    public function save(RackLocation $location): void
    {
        $location->save();
    }

    public function deleteLocation(RackWarehouse $rack): void
    {
        $rack->locations()->delete();
    }

    public function deleteRack(RackWarehouse $rack): void
    {
        $rack->delete();
    }

    /**
     * Get statistics for the rack dashboard cards.
     */
    public function getStatistics(): array
    {
        $warehouseId = Auth::user()->warehouse_id;

        $racks = RackWarehouse::withCount([
            'locations',
            'locations as available_locations_count' => function ($q) {
                $q->where('status', 'AVAILABLE');
            },
            'locations as full_locations_count' => function ($q) {
                $q->where('status', 'FULL');
            },
        ])->where('warehouse_id', $warehouseId)->get();

        $totalRacks = $racks->count();
        $racksAvailable = $racks->where('status', 'AVAILABLE')->count();
        $racksFull = $racks->where('status', 'FULL')->count();

        $totalBins = $racks->sum('locations_count');
        $binsAvailable = $racks->sum('available_locations_count');
        $binsFull = $racks->sum('full_locations_count');

        return [
            'total_racks'     => $totalRacks,
            'racks_available' => $racksAvailable,
            'racks_full'      => $racksFull,
            'total_bins'      => $totalBins,
            'bins_available'  => $binsAvailable,
            'bins_full'       => $binsFull,
        ];
    }
}
