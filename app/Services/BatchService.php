<?php

namespace App\Services;

use App\Models\Batch;
use App\Repositories\BatchRepository;
use BaconQrCode\Writer;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use Illuminate\Support\Facades\Storage;

class BatchService
{
    /**
     * Create a new class instance.
     */

    protected BatchRepository $batchRepository;

    public function __construct(BatchRepository $batchRepository)
    {
        $this->batchRepository = $batchRepository;
    }

    public function getAllBatches(int $batchPage = 10, array $filters)
    {
        return $this->batchRepository->getAllPaginated($batchPage, $filters);
    }

    public function getBatchDetail(Batch $batch): Batch
    {
        return $this->batchRepository->getById($batch);
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
                'rack_location' => $batch->rack?->location_code ?? 'N/A',
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
