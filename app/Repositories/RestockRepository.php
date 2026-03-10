<?php

namespace App\Repositories;

use App\Models\Restock;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestockRepository
{
    public function getAll(): Collection
    {
        return Restock::with('item.product', 'warehouse', 'request', 'confirm')->get();
    }

    public function create(array $data): Restock
    {
        $restock = Restock::create([
            'restock_code' => 'RC-' . now()->format('Ymd') . '-' . rand(1, 9999),
            'warehouse_id' => $data['warehouse_id'],
            'requested_by' => $data['requested_by'],
            'status'       => $data['status'] ?? 'PENDING',
            'notes'        => $data['notes'] ?? null,
        ]);

        foreach ($data['products'] as $item) {
            $restock->item()->create([
                'id' => (string) Str::ulid(),
                'product_id' => $item['id'],
                'requested_quantity' => $item['qty'],
            ]);
        }

        return $restock;
    }

    public function update(Restock $restock, array $data): Restock
    {
        $restock->update($data);

        if (isset($data['products'])) {
            $restock->product()->syncWithPivotValues(
                collect($data['products'])->pluck('qty', 'id')->toArray(),
                ['requested_quantity' => DB::raw('values(requested_quantity)')]
            );
        }

        return $restock->refresh();
    }

    public function delete(Restock $restock): bool
    {
        return $restock->delete();
    }

    public function confirm(Restock $restock, string $userId): Restock
    {
        $restock->update([
            'confirmed_by' => $userId,
            'status' => 'RESTOCKED'
        ]);
        // bisa juga buat mutasi stok di sini
        return $restock;
    }
}
