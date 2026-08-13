<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\StockReturns;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReturnController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = StockReturns::query()
            ->with(['product', 'request', 'warehouse'])
            ->latest();

        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('return_code', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($p) use ($search) {
                      $p->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('status') && $request->query('status') !== 'null' && $request->query('status') !== '') {
            $query->where('status', $request->query('status'));
        }

        $perPage = $request->query('per_page', 10);
        $returns = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Data returns berhasil diambil.',
            'data' => [
                'data' => collect($returns->items())->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'return_code' => $item->return_code,
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
                    'current_page' => $returns->currentPage(),
                    'last_page' => $returns->lastPage(),
                    'per_page' => $returns->perPage(),
                    'total' => $returns->total(),
                ]
            ]
        ]);
    }
}
