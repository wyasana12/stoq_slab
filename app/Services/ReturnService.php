<?php

namespace App\Services;

use App\Enums\ReturnStatus;
use App\Models\StockReturns;
use App\Repositories\ReturnRepository;

class ReturnService
{
    protected ReturnRepository $returnRepository;

    public function __construct(ReturnRepository $returnRepository)
    {
        $this->returnRepository = $returnRepository;
    }

    public function storeReturn(array $data): StockReturns
    {
        return $this->returnRepository->createReturn($data);
    }

    public function updateReturn(StockReturns $stockReturn, array $data): StockReturns
    {
        return $this->returnRepository->updateReturn($stockReturn, $data);
    }

    public function deleteReturn(StockReturns $stockReturn): void
    {
        $this->returnRepository->deleteReturn($stockReturn);
    }

    public function confirmReturn(StockReturns $stockReturn, array $data, string $confirmedBy): StockReturns
    {
        $newStatus = ReturnStatus::from($data['status']);

        return $this->returnRepository->updateStatus(
            $stockReturn,
            $newStatus,
            $data['approved_quantity'] ?? null,
            $confirmedBy,
            $data['notes'] ?? null
        );
    }
}
