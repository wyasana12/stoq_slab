<?php

namespace App\Services;

use App\Enums\ReceiveStatus;
use App\Models\ProductReceiving;
use App\Models\ProductReceivingItem;
use App\Models\PurchaseOrderItem;
use App\Repositories\ProductReceivingRepository;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    public function updateItemsAndStatus(ProductReceiving $receive, array $data, string $userId): ProductReceiving
    {
        return DB::transaction(function () use ($receive, $data, $userId) {
            $poItems = PurchaseOrderItem::where('purchase_id', $receive->purchase_id)
                ->get()
                ->keyBy('product_id');

            foreach ($data['items'] as $i) {
                $item = ProductReceivingItem::findOrFail($i['id']);
                $productId = $item->product_id;

                $poItem = $poItems[$productId] ?? null;

                if (!$poItem) {
                    throw new InvalidArgumentException("Product not found in purchase.");
                }

                $accepted = (int) $i['quantity_accepted'];
                $rejected = (int) $i['quantity_rejected'];

                $total = $accepted + $rejected;

                if ($total > $poItem->quantity_received) {
                    throw new InvalidArgumentException("Total quantity for {$item->products->name} cannot exceed {$poItem->quantity_received}.");
                }

                $this->productRecivingRepository->updateItems($item, [
                    'quantity_accepted' => $accepted,
                    'quantity_rejected' => $rejected,
                    'notes' => $i['notes'],
                ]);
            }

            if (isset($data['status'])) {

                $newStatus = ReceiveStatus::from($data['status']);

                if (!$receive->status->canTransition($newStatus)) {
                    throw new InvalidArgumentException(
                        "Status transition from '{$receive->status->value}' to '{$newStatus->value}' is not allowed."
                    );
                }

                $this->productRecivingRepository->updateStatus($receive, $newStatus, $userId);
            }

            return $receive->fresh(['items.products', 'user', 'purchase.warehouse']);
        });
    }

    public function softDeleteReceive(ProductReceiving $receive): void
    {
        $isProcess = $receive->status === ReceiveStatus::PROCESS;
        $isFinal = $receive->status->isFinal();

        if (!($isProcess || $isFinal)) {
            throw new InvalidArgumentException("The deletion rejected. The product receive must have a PENDING status or FINAL Transition.");
        }

        $this->productRecivingRepository->softDelete($receive);
    }

    public function getTrashedReceive(int $perPage)
    {
        return $this->productRecivingRepository->getTrashedPaginated($perPage);
    }

    public function restoreReceive(ProductReceiving $receive)
    {
        $this->productRecivingRepository->restore($receive);

        return $receive->fresh(['items.products', 'user', 'purchase.warehouse']);
    }

    public function forceDeleteReceive(ProductReceiving $receive)
    {
        $this->productRecivingRepository->forceDelete($receive);
    }
}
