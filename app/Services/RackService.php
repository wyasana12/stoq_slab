<?php

namespace App\Services;

use App\Models\RackLocation;
use App\Models\RackWarehouse;
use App\Repositories\RackRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RackService
{
    protected RackRepository $rackRepository;

    public function __construct(RackRepository $rackRepository)
    {
        $this->rackRepository = $rackRepository;
    }

    public function getAllRack(int $perPage = 10, ?string $search = null, ?string $status = null, ?string $categoryId = null)
    {
        return $this->rackRepository->getAllPaginated($perPage, $search, $status, $categoryId);
    }

    public function create(array $data): RackWarehouse
    {
        return DB::transaction(function () use ($data) {
            $warehouseId = Auth::user()->warehouse_id;
            $rackCode    = $this->generateRackCode($warehouseId);

            $rack = $this->rackRepository->create([
                'rack_code'    => $rackCode,
                'warehouse_id' => $warehouseId,
                'status'       => 'AVAILABLE',
            ]);

            $this->rackRepository->assignLocation(
                rack: $rack,
                levels: $data['levels'],
                bins_per_level: $data['bins_per_level'],
                capacity_unit: 'PCS',
                capacity: $data['capacity']
            );

            return $rack->load('locations');
        });
    }

    /**
     * Generate rack code in format RA0001, RA0002, etc.
     * Numbering resets per warehouse.
     */
    private function generateRackCode(string $warehouseId): string
    {
        $lastRack = RackWarehouse::where('warehouse_id', $warehouseId)
            ->where('rack_code', 'LIKE', 'RA-%')
            ->orderByRaw("CAST(SUBSTRING(rack_code, 4) AS UNSIGNED) DESC")
            ->first();

        $nextNumber = 1;

        if ($lastRack) {
            // Extract numeric part from e.g. "RA0005" → 5
            $numericPart = (int) substr($lastRack->rack_code, 3);
            $nextNumber  = $numericPart + 1;
        }

        return 'RA-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
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

    public function getStatistics(): array
    {
        return $this->rackRepository->getStatistics();
    }
}
