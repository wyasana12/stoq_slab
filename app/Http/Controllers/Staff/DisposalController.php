<?php
namespace App\Http\Controllers\Staff;
use App\Http\Controllers\Controller;
use App\Http\Requests\Disposal\StoreStockDisposalRequest;
use App\Http\Resources\DisposalResource;
use App\Models\StockDisposal;
use App\Services\DisposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
class DisposalController extends Controller
{
    protected DisposalService $disposalService;
    public function __construct(DisposalService $disposalService)
    {
        $this->disposalService = $disposalService;
    }
    public function index(Request $request): JsonResponse
    {
        $query = StockDisposal::query()
            ->with(['warehouse', 'product', 'request', 'confirm'])
            ->latest();
        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->has('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('disposal_code', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }
        $disposals = $query->get();
        return response()->json([
            'success' => true,
            'data' => DisposalResource::collection($disposals),
            'stats' => [
                'total' => $disposals->count(),
                'requested' => $disposals->where('status', 'requested')->count(),
                'approved' => $disposals->where('status', 'approved')->count(),
                'rejected' => $disposals->where('status', 'rejected')->count(),
            ]
        ]);
    }
    public function show(StockDisposal $stockDisposal): JsonResponse
    {
        $stockDisposal->load(['warehouse', 'product', 'request', 'confirm']);
        return response()->json([
            'success' => true,
            'data' => new DisposalResource($stockDisposal),
        ]);
    }
    public function store(StoreStockDisposalRequest $request): JsonResponse
    {
        try {
            $payload = $request->validated();
            $payload['warehouse_id'] = $request->user()->warehouse_id;
            if ($request->hasFile('damage_proof')) {
                $payload = array_merge($payload, $this->saveProof($request->file('damage_proof')));
            }
            $disposal = $this->disposalService->storeDisposal($payload, $request->user()->id);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }
        return response()->json([
            'message' => 'Request pemusnahan berhasil dibuat.',
            'data' => new DisposalResource($disposal),
        ], 201);
    }
    private function saveProof($file): array
    {
        $path = $file->store('disposal_proofs', 'public');
        return [
            'damage_proof_path' => $path,
            'damage_proof_name' => $file->getClientOriginalName(),
            'damage_proof_mime' => $file->getClientMimeType(),
            'damage_proof_size' => $file->getSize(),
            'damage_proof_uploaded_at' => now(),
        ];
    }
}