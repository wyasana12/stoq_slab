<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\RackLocation;
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

    public function getSummary()
    {
        return $this->batchRepository->getSummary();
    }

    public function getAllBatches()
    {
        return $this->batchRepository->getAll();
    }

    public function getBatchDetail(Batch $batch): Batch
    {
        return $this->batchRepository->getById($batch);
    }

    public function assignLocation(Batch $batch, array $manualLocation = []): void
    {
        DB::transaction(function () use ($batch, $manualLocation) {
            $remaining = $batch->current_quantity;

            if ($remaining <= 0) return;

            $query = RackLocation::select('rack_locations.*')
                ->join('rack_warehouses', 'rack_warehouses.id', '=', 'rack_locations.rack_id')
                ->where('rack_warehouses.warehouse_id', $batch->warehouse_id)
                ->where(function ($q) {
                    $q->whereNull('rack_warehouses.status')
                        ->orWhereNotIn('rack_warehouses.status', ['INACTIVE', 'MAINTENANCE']);
                })
                ->where(function ($q) {
                    $q->whereNull('rack_locations.status')
                        ->orWhereNotIn('rack_locations.status', ['BLOCKED', 'MAINTENANCE']);
                })
                ->where(function ($query) use ($batch) {
                    $query->whereNull('rack_locations.batch_id')
                        ->orWhere('rack_locations.batch_id', $batch->id);
                })
                ->whereRaw('rack_locations.capacity > rack_locations.used')
                ->lockForUpdate();

            if (!empty($manualLocation)) {
                $availableLocations = (clone $query)
                    ->whereIn('rack_locations.id', $manualLocation)
                    ->get();

                $availableLocations = $availableLocations->sortBy(function ($loc) use ($manualLocation) {
                    return array_search($loc->id, $manualLocation);
                });
            } else {
                $availableLocations = (clone $query)->orderBy('rack_warehouses.rack_code', 'asc')
                    ->orderBy('rack_locations.level', 'asc')
                    ->orderBy('rack_locations.bin', 'asc')
                    ->get();
            }

            $totalCapacityAvailable = 0;
            foreach ($availableLocations as $loc) {
                $totalCapacityAvailable += max(0, $loc->capacity - $loc->used);
            }

            if ($totalCapacityAvailable < $remaining) {
                $modeTxt = !empty($manualLocation) ? "yang Anda pilih" : "tersedia";
                throw new InvalidArgumentException(
                    "Kapasitas lokasi rak {$modeTxt} tidak mencukupi untuk Batch {$batch->batch_code}. Qty butuh: {$remaining}, Total kapasitas: {$totalCapacityAvailable}."
                );
            }

            foreach ($availableLocations as $location) {
                if ($remaining <= 0) break;

                $availableSpace = max(0, $location->capacity - $location->used);

                if ($availableSpace === 0) continue;

                $qtyToStore = min($remaining, $availableSpace);

                $location->batch_id = $batch->id;
                $location->used += $qtyToStore;

                if ($location->used >= $location->capacity) {
                    $location->status = 'FULL';
                } elseif ($location->used > 0) {
                    $location->status = 'PARTIAL';
                }

                $this->rackRepository->save($location);

                $remaining -= $qtyToStore;
            }
        });
    }

    public function releaseLocation(Batch $batch, int $qtyToRelease): void
    {
        DB::transaction(function () use ($batch, $qtyToRelease) {
            if ($qtyToRelease <= 0) {
                throw new InvalidArgumentException('Quantity to release must be greater than zero.');
            }

            // Dapatkan semua lokasi yang dipakai oleh batch ini, urutkan dari isinya yang paling sedikit (asc)
            // Tujuannya agar bin yang sedikit isinya dikosongkan lebih dahulu, sehingga membebaskan bin secara utuh
            $locations = $batch->locations()->orderBy('used', 'asc')->lockForUpdate()->get();

            $totalUsed = $locations->sum('used');

            if ($totalUsed < $qtyToRelease) {
                throw new InvalidArgumentException("Kuantitas yang akan dikeluarkan ({$qtyToRelease}) melebihi total barang yang ada di rak ({$totalUsed}).");
            }

            $remainingToRelease = $qtyToRelease;

            foreach ($locations as $location) {
                if ($remainingToRelease <= 0) {
                    break;
                }

                $qtyDeducted = min($location->used, $remainingToRelease);

                $location->used -= $qtyDeducted;
                $remainingToRelease -= $qtyDeducted;

                if ($location->used == 0) {
                    $location->status = 'AVAILABLE';
                    $location->batch_id = null;
                } elseif ($location->used < $location->capacity) {
                    $location->status = 'PARTIAL';
                }

                $this->rackRepository->save($location);
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

    public function destroy(Batch $batch)
    {
        if (in_array($batch->condition, ['BAIK', 'MENDEKATI_KADALUARSA'])) {
            throw new InvalidArgumentException("Batch tidak bisa dihapus jika kondisinya baik atau mendekati expired.");
        }

        $this->batchRepository->destroy($batch);
    }
}
