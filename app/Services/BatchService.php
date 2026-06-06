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
        $data = json_encode($batch->toArray());

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );

        $writer = new Writer($renderer);

        $svg = $writer->writeString($data);

        $path = "qrcodes/batch-{$batch->batch_code}.svg";

        Storage::disk('public')->put($path, $svg);

        return $this->batchRepository->updateBarcode($batch, $path);
    }

    public function getSelectedForPrint(array $batchIds)
    {
        return $this->batchRepository->getSelectedForPrint($batchIds);    
    }
}
