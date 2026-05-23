<?php

namespace App\Repositories;

use App\Models\StockMutations;

class MutationRepository
{
    public function create(array $data): bool {
        return StockMutations::insert($data);
    }
}
