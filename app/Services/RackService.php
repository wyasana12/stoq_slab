<?php

namespace App\Services;

use App\Models\RackLocation;
use App\Models\RackWarehouse;
use App\Repositories\RackRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RackService
{
    protected RackRepository $rackRepository;

    public function __construct(RackRepository $rackRepository)
    {
        $this->rackRepository = $rackRepository;
    }

    public function getAllRack(int $rackPage = 10)
    {
        return $this->rackRepository->getAllPaginated($rackPage);
    }

    public function create(array $data): RackWarehouse
    {
        return DB::transaction(function () use ($data) {
            $rack = $this->rackRepository->create([
                'rack_code' => $data['rack_code'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => $data['status'],
            ]);

            $this->rackRepository->assignLocation(
                rack: $rack,
                levels: $data['levels'],
                bins_per_level: $data['bins_per_level'],
                capacity_unit: $data['capacity_unit'],
                capacity: $data['capacity']
            );

            return $rack;
        });
    }

    public function getRackDetail(RackWarehouse $rack): RackWarehouse
    {
        return $this->rackRepository->getById($rack);
    }

    public function updateLocation(RackLocation $location, array $data): RackLocation
    {
        if (isset($data['capacity']) && $data['capacity'] < $location->used) {
            throw new InvalidArgumentException("Capacity cannot be less than used capacity $location->used");
        }

        $this->rackRepository->update($location, $data);

        return $location->fresh();
    }

    public function deleteRack(RackWarehouse $rack): void
    {
        DB::transaction(function () use ($rack) {
            $this->rackRepository->deleteLocation($rack);
            $this->rackRepository->deleteRack($rack);
        });
    }
}
