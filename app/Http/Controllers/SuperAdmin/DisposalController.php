<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\StockDisposal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DisposalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StockDisposal::query()
            ->with(['product', 'request', 'warehouse'])
            ->latest();

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('disposal_code', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($p) use ($search) {
                      $p->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->query('status') !== 'null' && $request->query('status') !== '') {
            $query->where('status', $request->query('status'));
        }

        $perPage = $request->query('per_page', 10);
        $disposals = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Data disposals berhasil diambil.',
            'data' => [
                'data' => collect($disposals->items())->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'disposal_code' => $item->disposal_code,
                        'product_name' => $item->product ? $item->product->name : null,
                        'warehouse_name' => $item->warehouse ? $item->warehouse->name : null,
                        'requested_quantity' => $item->requested_quantity,
                        'reason' => $item->reason,
                        'status' => $item->status,
                        'created_at' => $item->created_at,
                        'requested_by_name' => $item->request ? $item->request->name : null,
                        'notes' => $item->notes,
                        'damage_proof_url' => $item->damage_proof_path ? asset('storage/' . $item->damage_proof_path) : null,
                    ];
                }),
                'meta' => [
                    'current_page' => $disposals->currentPage(),
                    'last_page' => $disposals->lastPage(),
                    'per_page' => $disposals->perPage(),
                    'total' => $disposals->total(),
                ]
            ]
        ]);
    }
}
