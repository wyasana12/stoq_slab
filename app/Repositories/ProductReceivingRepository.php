<?php

namespace App\Repositories;

use App\Enums\ReceiveStatus;
use App\Models\ProductReceiving;
use App\Models\ProductReceivingItem;
use App\Models\PurchaseOrder;
use App\Models\Restock;
use App\Models\StockTransfers;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use function Illuminate\Support\now;
use Illuminate\Support\Str;

class ProductReceivingRepository
{
    public function getStats(): array
    {
        $userId = Auth::user()->warehouse_id;

        $baseQuery = ProductReceiving::whereHasMorph(
            'receivable',
            [PurchaseOrder::class, StockTransfers::class, Restock::class],
            function ($q, $type) use ($userId) {
                if ($type === StockTransfers::class) {
                    $q->where('to_warehouse_id', $userId);
                } else {
                    $q->where('warehouse_id', $userId);
                }
            }
        );

        $pendingStats =
            PurchaseOrder::where('warehouse_id', $userId)->where('status', 'ordered')->count() +
            StockTransfers::where('to_warehouse_id', $userId)->where('status', 'on_delivery')->count() +
            Restock::where('warehouse_id', $userId)->where('status', 'on_delivery')->count();

        return [
            'total_receive' => (clone $baseQuery)->count(),
            'receive_in_fully' => (clone $baseQuery)->where('status', ReceiveStatus::FULL)->count(),
            'receive_in_partially' => (clone $baseQuery)->where('status', ReceiveStatus::PARTIAL)->count(),
            'pending_documents' => $pendingStats
        ];
    }
    public function getAll()
    {
        $userId = Auth::user()->warehouse_id;

        $query = ProductReceiving::with(['receivable', 'user:id,name'])->select('id', 'receiving_code', 'receiving_date', 'status', 'receivable_type', 'receivable_id', 'receiving_by');

        return $query->whereHasMorph(
            'receivable',
            [PurchaseOrder::class, StockTransfers::class, Restock::class],
            function ($q, $type) use ($userId) {
                if ($type === StockTransfers::class) {
                    $q->where('to_warehouse_id', $userId);
                } else {
                    $q->where('warehouse_id', $userId);
                }
            }
        )->latest()->get();
    }

    public function getById(ProductReceiving $receive): ProductReceiving
    {
        return $receive->load(['receivable', 'user', 'items.products']);
    }

    public function createReceive(array $data): ProductReceiving
    {

        return ProductReceiving::create($data);
    }

    public function assignItems(ProductReceiving $receive, array $items): void
    {
        $insertData = [];

        foreach ($items as $i) {
            $insertData[] = [
                'id' => (string) Str::ulid(),
                'receiving_id' => $receive->id,
                'product_id' => $i['product_id'],
                'quantity_accepted' => $i['quantity_accepted'],
                'quantity_rejected' => $i['quantity_rejected'],
                'notes' => $i['notes'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        ProductReceivingItem::insert($insertData);
    }

    public function deleteItems(ProductReceiving $receive): void
    {
        $receive->items()->delete();
    }

    public function updateItems(ProductReceivingItem $item, array $data): ProductReceivingItem
    {
        $item->update([
            'quantity_accepted' => $data['quantity_accepted'],
            'quantity_rejected' => $data['quantity_rejected'],
            'notes' => $data['notes'],
        ]);

        return $item;
    }

    public function updateStatus(ProductReceiving $receive, ReceiveStatus $newStatus, ?string $userId): void
    {
        if (!$receive->status->canTransition($newStatus)) {
            throw new InvalidArgumentException("Status transition from '{$receive->status->value}' to '{$newStatus->value}' is not allowed.");
        }

        $updateData = ['status' => $newStatus];

        if (in_array($newStatus, [ReceiveStatus::FULL, ReceiveStatus::PARTIAL])) {
            if (!$userId) {
                throw new InvalidArgumentException("Receiving user is required.");
            }

            $updateData['receiving_date'] = now();
            $updateData['receiving_by'] = $userId;
        }

        $receive->update($updateData);
    }

    public function softDelete(ProductReceiving $receive): void
    {
        $receive->delete();
    }

    public function getTrashedPaginated()
    {
        return ProductReceiving::onlyTrashed()
            ->with(['receivable', 'user:id,name'])
            ->latest('deleted_at')
            ->get();
    }

    public function restore(ProductReceiving $receive): void
    {
        $receive->restore();
    }

    public function forceDelete(ProductReceiving $receive): void
    {
        $receive->items()->delete();
        $receive->forceDelete();
    }
}
