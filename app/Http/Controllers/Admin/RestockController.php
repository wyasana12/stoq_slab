<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restock\StoreRestockRequest;
use App\Http\Resources\RestockResource;
use App\Models\Restock;
use App\Repositories\RestockRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RestockController extends Controller
{
    public function __construct(
        protected RestockRepository $repository
    ) {}

    public function index(): JsonResponse
    {
        $restocks = $this->repository->getAll();
        return response()->json([
            'success' => true,
            'data' => RestockResource::collection($restocks),
        ]);
    }

    public function store(StoreRestockRequest $request): JsonResponse
    {
        $data = $request->validated();

        $restock = $this->repository->create(array_merge($data, [
            'notes'  => $data['notes'] ?? null,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Restock created',
            'data' => new RestockResource($restock),
        ]);
    }

    public function show(Restock $restock): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new RestockResource($restock),
        ]);
    }

    public function update(StoreRestockRequest $request, Restock $restock): JsonResponse
    {
        try {
            $restock = $this->repository->update($restock, $request->validated());
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Restock updated',
            'data' => new RestockResource($restock),
        ]);
    }

    public function destroy(Restock $restock): JsonResponse
    {
        $this->repository->delete($restock);
        return response()->json([
            'success' => true,
            'message' => 'Restock deleted',
        ]);
    }
}
