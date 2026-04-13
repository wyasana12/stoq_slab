<?php

namespace App\Services;

use App\Models\ProductReceiving;
use App\Repositories\ProductReceivingRepository;

class ProductReceivingService
{
    /**
     * Create a new class instance.
     */

    protected $productRecivingRepository;

    public function __construct(ProductReceivingRepository $productRecivingRepository)
    {
        $this->productRecivingRepository = $productRecivingRepository;
    }

    public function getAllReceives(int $receivePage = 10)
    {
        return $this->productRecivingRepository->getAllPaginated($receivePage);    
    }

    public function getReceiveDetail(ProductReceiving $receive): ProductReceiving
    {
        return $this->productRecivingRepository->getById($receive);    
    }
}
