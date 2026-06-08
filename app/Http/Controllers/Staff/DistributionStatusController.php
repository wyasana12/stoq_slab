<?php

namespace App\Http\Controllers\Staff;

use App\Enums\DistributionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Distribution\ConfirmDistributionRequest;
use App\Http\Resources\DistributionResource;
use App\Models\StockDistributions;
use App\Repositories\DistributionRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class DistributionStatusController extends Controller
{
    public function __construct(
        protected DistributionRepository $repository
    ) {}

    public function confirm(ConfirmDistributionRequest $request, StockDistributions $distribution): JsonResponse
    {
        try {
            $status = DistributionStatus::from($request->validated('status'));

            if ($status->name === 'SHIPPED' && $request->hasFile('shipped_proof')) {
                $this->saveProof($distribution, $request->file('shipped_proof'), 'shipped');
            }

            if ($status->name === 'COMPLETED' && $request->hasFile('completed_proof')) {
                $this->saveProof($distribution, $request->file('completed_proof'), 'completed');
            }

            $distribution = $this->repository->updateStatus(
                $distribution,
                $status,
                $request->validated('confirmed_by'),
                $request->validated('notes'),
                $request->validated('items')
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Distribusi berhasil dikonfirmasi.',
            'data' => new DistributionResource($distribution->refresh()),
        ]);
    }

    public function downloadShippedProof(StockDistributions $distribution)
    {
        if (! $distribution->shipped_proof_path) {
            return response()->json([
                'success' => false,
                'message' => 'Bukti pengiriman shipped tidak ditemukan.',
            ], 404);
        }

        return response()->download(
            Storage::disk('public')->path($distribution->shipped_proof_path),
            $distribution->shipped_proof_name
        );
    }

    public function downloadCompletedProof(StockDistributions $distribution)
    {
        if (! $distribution->completed_proof_path) {
            return response()->json([
                'success' => false,
                'message' => 'Bukti pengiriman completed tidak ditemukan.',
            ], 404);
        }

        return response()->download(
            Storage::disk('public')->path($distribution->completed_proof_path),
            $distribution->completed_proof_name
        );
    }

    private function saveProof(StockDistributions $distribution, $file, string $type): void
    {
        $path = $file->store('distribution_proofs', 'public');

        $distribution->update([
            "{$type}_proof_path" => $path,
            "{$type}_proof_name" => $file->getClientOriginalName(),
            "{$type}_proof_mime" => $file->getClientMimeType(),
            "{$type}_proof_size" => $file->getSize(),
            "{$type}_proof_uploaded_at" => now(),
        ]);
    }

    public function allowedTransitions(StockDistributions $distribution): JsonResponse
    {
        $currentStatus = DistributionStatus::from($distribution->status);
        $allowedStatuses = [];

        foreach (DistributionStatus::cases() as $status) {
            if ($currentStatus->canTransition($status)) {
                $allowedStatuses[] = [
                    'status' => $status->value,
                    'label' => $this->getStatusLabel($status),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'current_status' => $currentStatus->value,
            'current_status_label' => $this->getStatusLabel($currentStatus),
            'allowed_transitions' => $allowedStatuses,
        ]);
    }

    private function getStatusLabel(DistributionStatus $status): string
    {
        // Use the enum case name to avoid referencing undefined constants
        return match (strtoupper($status->name)) {
            'PENDING' => 'Pending',
            'SHIPPED' => 'Shipped',
            'COMPLETED' => 'Completed',
            'CANCELLED' => 'Cancelled',
            default => ucfirst(strtolower($status->name)),
        };
    }
    public function downloadSuratJalanTemplate()
    {
        $path = storage_path('app/public/templates/surat_jalan.docx');

        if (! file_exists($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Template Surat Jalan tidak ditemukan.',
            ], 404);
        }

        return response()->download($path, 'Surat Jalan Template.docx');
    }
}
