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
use Illuminate\Http\Request;

class DistributionStatusController extends Controller
{
    public function __construct(
        protected DistributionRepository $repository,
        protected \App\Services\DistributionNotificationService $notificationService
    ) {}

    public function confirm(ConfirmDistributionRequest $request, StockDistributions $distribution): JsonResponse
    {
        try {
            $status = DistributionStatus::from($request->validated('status'));

            if ($status->name === 'SHIPPED' && $request->hasFile('shipped_proof')) {
                $this->saveProofs($distribution, $request->file('shipped_proof'), 'shipped');
            }

            if ($status->name === 'COMPLETED' && $request->hasFile('completed_proof')) {
                $this->saveProofs($distribution, $request->file('completed_proof'), 'completed');
            }

            $distribution = $this->repository->updateStatus(
                $distribution,
                $status,
                $request->validated('confirmed_by'),
                $request->validated('notes'),
                $request->validated('items')
            );
            
            $this->notificationService->sendDistributionNotification($distribution, $status);
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

    public function downloadShippedProof(StockDistributions $distribution, Request $request)
    {
        $proofs = is_string($distribution->shipped_proofs) ? json_decode($distribution->shipped_proofs, true) : ($distribution->shipped_proofs ?? []);
        $index = $request->query('index', 0);

        if (empty($proofs) || !isset($proofs[$index])) {
            return response()->json([
                'success' => false,
                'message' => 'Bukti pengiriman shipped tidak ditemukan.',
            ], 404);
        }

        return response()->download(
            Storage::disk('public')->path($proofs[$index]),
            basename($proofs[$index])
        );
    }

    public function downloadCompletedProof(StockDistributions $distribution, Request $request)
    {
        $proofs = is_string($distribution->completed_proofs) ? json_decode($distribution->completed_proofs, true) : ($distribution->completed_proofs ?? []);
        $index = $request->query('index', 0);

        if (empty($proofs) || !isset($proofs[$index])) {
            return response()->json([
                'success' => false,
                'message' => 'Bukti pengiriman completed tidak ditemukan.',
            ], 404);
        }

        return response()->download(
            Storage::disk('public')->path($proofs[$index]),
            basename($proofs[$index])
        );
    }

    private function saveProofs(StockDistributions $distribution, $files, string $type): void
    {
        if (!is_array($files)) {
            $files = [$files];
        }

        $existingProofs = $distribution->{"{$type}_proofs"} ?? [];
        if (is_string($existingProofs)) {
            $existingProofs = json_decode($existingProofs, true) ?? [];
        }

        foreach ($files as $file) {
            $path = $file->store('distribution_proofs', 'public');
            $existingProofs[] = $path;
        }

        $distribution->update([
            "{$type}_proofs" => $existingProofs,
        ]);
    }

    public function allowedTransitions(StockDistributions $distribution): JsonResponse
    {
        $currentStatus = $distribution->status instanceof \BackedEnum ? $distribution->status : DistributionStatus::from($distribution->status);
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
    public function downloadSuratJalan(StockDistributions $distribution)
    {
        // Generate surat_jalan_no if not exists
        if (empty($distribution->surat_jalan_no)) {
            // Find the latest surat_jalan_no in this month to increment
            $monthPrefix = 'SJ/' . now()->format('Y/m') . '/';
            $latest = \App\Models\StockDistributions::where('surat_jalan_no', 'like', $monthPrefix . '%')
                ->orderBy('surat_jalan_no', 'desc')
                ->first();

            if ($latest && $latest->surat_jalan_no) {
                $lastNumber = intval(substr($latest->surat_jalan_no, -4));
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $suratJalanNo = $monthPrefix . $newNumber;

            $distribution->update([
                'surat_jalan_no' => $suratJalanNo,
                'flag_print' => true
            ]);
        } else {
            $distribution->update([
                'flag_print' => true
            ]);
        }

        // Load the relationship for the view
        $distribution->load(['warehouse', 'store', 'items.batch.product.unit']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.surat_jalan', compact('distribution'));

        return $pdf->download('Surat_Jalan_' . str_replace('/', '_', $distribution->surat_jalan_no) . '.pdf');
    }
}
