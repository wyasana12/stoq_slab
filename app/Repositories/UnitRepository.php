<?php

namespace App\Repositories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;

class UnitRepository
{
    public function getAllUnits(): Collection
    {
        return Unit::select('id', 'name', 'symbol')->get();
    }

    public function createUnit(array $data): Unit
    {
        return Unit::create($data);
    }

    public function updateUnit(Unit $unit, array $data): Unit
    {
        $unit->update($data);

        return $unit;
    }

    public function deleteUnit(Unit $unit): bool
    {
        return $unit->delete();    
    }
}
