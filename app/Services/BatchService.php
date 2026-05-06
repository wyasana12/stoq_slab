<?php

namespace App\Services;

use App\Models\Batch;
use App\Repositories\BatchRepository;

class BatchService
{
    /**
     * Create a new class instance.
     */

    protected $batchRepository;

    public function __construct(BatchRepository $batchRepository)
    {
        $this->batchRepository = $batchRepository;
    }

    public function getAllBatches(int $batchPage = 10)
    {
        return $this->batchRepository->getAllPaginated($batchPage);    
    }

    public function getBatchDetail(Batch $batch): Batch
    {
        return $this->batchRepository->getById($batch);    
    }
}
