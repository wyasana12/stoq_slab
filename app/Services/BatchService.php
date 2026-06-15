<?php

namespace App\Services;

use App\Models\Batch;
use App\Repositories\BatchRepository;
use App\Repositories\RackRepository;
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class BatchService
{
    /**
     * Create a new class instance.
     */

    protected BatchRepository $batchRepository;
    protected RackRepository $rackRepository;

    public function __construct(BatchRepository $batchRepository, RackRepository $rackRepository)
    {
        $this->batchRepository = $batchRepository;
        $this->rackRepository = $rackRepository;
    }

    public function getAllBatches(int $batchPage = 10, array $filters)
    {
        return $this->batchRepository->getAllPaginated($batchPage, $filters);
    }

    public function getBatchDetail(Batch $batch): Batch
    {
        return $this->batchRepository->getById($batch);
    }

    public function assignLocation(Batch $batch, array $data): void
    {
        DB::transaction(function () use ($batch, $data) {
            if (empty($data)) {
                throw new InvalidArgumentException('Please select at least one rack location.');
            }

            $remaining = $batch->current_quantity;

            $locations = collect($data)->map(function ($item) {
                return $this->rackRepository
                    ->locationById($item['location_id']);
            });

            $first = $locations->first();

            $available = max(
                0,
                $first->capacity - $first->used,
            );

            if ($available >= $remaining && count($data) > 1) {
                throw new InvalidArgumentException("Please choose only one bin because the first bin has sufficient capacity.");
            }

            $totalAvailable = 0;

            foreach ($locations as $location) {
                if (
                    $location->batch_id !== null &&
                    $location->batch_id !== $batch->id
                ) {
                    throw new InvalidArgumentException(
                        "Bin {$location->location_code} is already occupied."
                    );
                }

                $totalAvailable += max(
                    0,
                    $location->capacity - $location->used
                );
            }

            foreach ($locations as $location) {
                if ($remaining < 0) break;

                $available = max(
                    0,
                    $location->capacity - $location->used,
                );

                if ($available === 0) continue;

                $qty = min($remaining, $available);

                $location->batch_id = $batch->id;
                $location->used += $qty;

                if ($location->used >= $location->capacity) {
                    $location->status = 'FULL';
                } elseif ($location->used > 0) {
                    $location->status = 'PARTIAL';
                } else {
                    $location->status = 'AVAILABLE';
                }

                $this->rackRepository->save($location);

                $remaining -= $qty;
            }
        });
    }

    public function generateBarcode(Batch $batch): Batch
    {
        $data = [
            'id' => $batch->id,
            'batch_code' => $batch->batch_code,
            'product' => [
                'id' => $batch->product?->id ?? 'N/A',
                'name' => $batch->product?->name ?? 'N/A',
                'current_quantity' => $batch->current_quantity,
                'price' => $batch->price,
                'condition' => $batch->condition,
                'rack_location' => $batch->locations->first()?->location_code ?? 'N/A',
                'production_date' => $batch->production_date ? $batch->production_date->format('l, d F Y') : 'N/A',
                'expired_date' => $batch->expired_date ? $batch->expired_date->format('l, d F Y') : 'N/A',
            ],
        ];

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );

        $writer = new Writer($renderer);

        $svg = $writer->writeString(json_encode($data));

        $path = "qrcodes/batch-{$batch->batch_code}.svg";

        Storage::disk('public')->put($path, $svg);

        return $this->batchRepository->updateBarcode($batch, $path);
    }

    public function getSelectedForPrint(array $batchIds)
    {
        return $this->batchRepository->getSelectedForPrint($batchIds);
    }
}
