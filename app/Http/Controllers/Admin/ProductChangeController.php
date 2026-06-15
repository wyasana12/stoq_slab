<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StockMutations;
use Illuminate\Http\Request;

class ProductChangeController extends Controller
{
    /**
     * Display a listing of product changes (mutations).
     */
    public function index(Request $request)
    {
        $query = StockMutations::with(['warehouse', 'batch.product.unit'])
            ->latest();

        if ($request->user() && $request->user()->warehouse_id) {
            $query->where('warehouse_id', $request->user()->warehouse_id);
        }

        $mutations = $query->get();

        $data = $mutations->map(function ($mutation) {
            $isIn = $mutation->after_quantity > $mutation->before_quantity;
            $type = $isIn ? 'in' : 'out';
            $typeLabel = $isIn ? 'Stok Masuk' : 'Stok Keluar';
            
            $referenceLabels = [
                'DISTRIBUTION' => 'Distribusi',
                'TRANSFER' => 'Transfer',
                'RESTOCK' => 'Restock',
                'RETURN' => 'Retur',
                'RECEIVE' => 'Penerimaan',
            ];
            $refLabel = $referenceLabels[$mutation->reference_type] ?? $mutation->reference_type;
            
            $description = $isIn ? "Stok masuk dari {$refLabel}" : "Stok keluar untuk {$refLabel}";
            if ($mutation->notes) {
                $description .= " - " . $mutation->notes;
            }

            return [
                'id' => $mutation->id,
                'created_at' => $mutation->created_at,
                'warehouse_name' => $mutation->warehouse?->name,
                'batch_code' => $mutation->batch?->batch_code,
                'product_name' => $mutation->batch?->product?->name,
                'product_sku' => $mutation->batch?->product?->sku,
                'product_unit' => $mutation->batch?->product?->unit?->name ?? 'unit',
                'change_quantity' => $mutation->change_quantity,
                'before_quantity' => $mutation->before_quantity,
                'after_quantity' => $mutation->after_quantity,
                'reference_type' => $mutation->reference_type,
                'reference_id' => $mutation->reference_id,
                'mutation_type' => $type,
                'mutation_type_label' => $typeLabel,
                'description' => $description,
                'notes' => $mutation->notes,
                'status' => $mutation->status,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
