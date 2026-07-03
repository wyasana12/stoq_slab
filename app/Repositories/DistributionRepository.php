<?php

namespace App\Repositories;

use App\Enums\DistributionStatus;
use App\Enums\RoleName;
use App\Models\Batch;
use App\Models\Store;
use App\Models\StockDistributions;
use App\Models\StockDistributionItem;
use App\Models\StockMutations;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Notification;
use App\Notifications\DistributionCreatedNotification;
use App\Notifications\ReturnNotification;
use App\Notifications\DisposalNotification;
use App\Notifications\DistributionReplacementNotification;
use App\Enums\MutationStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use App\Services\BatchService;

class DistributionRepository
{
    public function __construct(protected BatchService $batchService) {}

    public function getAllDistributions(?string $warehouseId = null): Collection
    {
        $query = StockDistributions::with(['items.batch', 'warehouse', 'request', 'confirmedBy'])
            ->orderBy('created_at', 'desc');

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->get();
    }

    public function createDistribution(array $data): StockDistributions
    {
        return DB::transaction(function () use ($data) {
            $requestedBy = $data['requested_by'] ?? Auth::id();

            $status = DistributionStatus::DRAFT;

            if (! empty($data['submit_for_approval'])) {
                $status = DistributionStatus::WAITING_APPROVAL;
            } elseif (($data['status'] ?? null) === DistributionStatus::WAITING_APPROVAL->value) {
                $status = DistributionStatus::WAITING_APPROVAL;
            }

            // If a store_id is provided, pull outlet data from the Store master table
            if (! empty($data['store_id'])) {
                $store = Store::find($data['store_id']);
                if ($store) {
                    $data['outlet_name'] = $store->name;
                    $data['outlet_address'] = $store->address;
                    $data['outlet_phone'] = $store->phone;
                    $data['outlet_contact'] = $store->phone; // keep contact consistent
                }
            }

            $distribution = StockDistributions::create([
                'distribution_code' => 'DIST-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                'warehouse_id'      => $data['warehouse_id'],
                'store_id'          => $data['store_id'] ?? null,  // ← tambah ini

                'outlet_name'       => $data['outlet_name'] ?? null,
                'outlet_address'    => $data['outlet_address'] ?? null,
                'outlet_phone'      => $data['outlet_phone'] ?? $data['outlet_contact'] ?? null,
                'outlet_contact'    => $data['outlet_contact'] ?? $data['outlet_phone'] ?? null,
                'dispatched_at'     => null,
                'requested_by'      => $requestedBy,
                'confirmed_by'      => $data['confirmed_by'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'status'            => $status,
            ]);
            foreach ($data['items'] as $item) {
                StockDistributionItem::create([
                    'distribution_id' => $distribution->id,
                    'batch_id' => $item['batch_id'],
                    'requested_quantity' => $item['requested_quantity'],
                    'approved_quantity' => $item['approved_quantity'] ?? 0,
                ]);
            }

            return $distribution->load('items.batch', 'warehouse', 'request', 'confirmedBy');
        });
    }

    public function updateDistribution(StockDistributions $distribution, array $data): StockDistributions
    {
        return DB::transaction(function () use ($distribution, $data) {
            if (! in_array($distribution->status, [
                DistributionStatus::DRAFT,
                DistributionStatus::WAITING_APPROVAL,
            ], true)) {
                throw new InvalidArgumentException('Distribusi hanya dapat diubah saat draft atau waiting approval.');
            }

            $currentStatus = $distribution->status;

            if (isset($data['status']) && $data['status'] !== $distribution->status->value) {
                $newStatus = DistributionStatus::from($data['status']);

                if (! $currentStatus->canTransition($newStatus)) {
                    throw new InvalidArgumentException(
                        "Transisi status {$distribution->status->value} ke {$data['status']} tidak diizinkan."
                    );
                }

                $distribution->status = $newStatus;
            }


            $distribution->update([
                'warehouse_id' => $data['warehouse_id'] ?? $distribution->warehouse_id,
                'requested_by' => $data['requested_by'] ?? $distribution->requested_by,
                'confirmed_by' => $data['confirmed_by'] ?? $distribution->confirmed_by,
                'notes' => $data['notes'] ?? $distribution->notes,
                'status' => $distribution->status,
            ]);

            if (isset($data['items']) && is_array($data['items'])) {
                $distribution->items()->delete();

                foreach ($data['items'] as $item) {
                    StockDistributionItem::create([
                        'distribution_id' => $distribution->id,
                        'batch_id' => $item['batch_id'],
                        'requested_quantity' => $item['requested_quantity'],
                        'approved_quantity' => $item['approved_quantity'] ?? 0,
                    ]);
                }
            }

            return $distribution->refresh()->load('items.batch', 'warehouse', 'request', 'confirmedBy');
        });
    }

    public function updateStatus(
        StockDistributions $distribution,
        DistributionStatus $newStatus,
        ?string $confirmedBy = null,
        ?string $notes = null,
        ?array $items = null
    ): StockDistributions {
        return DB::transaction(function () use ($distribution, $newStatus, $confirmedBy, $notes, $items) {
            $currentStatus = $distribution->status;

            if (! $currentStatus) {
                throw new InvalidArgumentException('Status distribusi saat ini tidak valid.');
            }

            if (! $currentStatus->canTransition($newStatus)) {
                throw new InvalidArgumentException(
                    "Transisi status {$currentStatus->value} ke {$newStatus->value} tidak diizinkan."
                );
            }

            if ($newStatus === DistributionStatus::APPROVED) {
                if (! is_array($items) || count($items) === 0) {
                    throw new InvalidArgumentException('Approved quantities harus diisi saat approve distribusi.');
                }

                $distributionItems = $distribution->items()->get()->keyBy('id');

                foreach ($items as $item) {
                    $distributionItem = $distributionItems[$item['id']] ?? null;
                    if (! $distributionItem) {
                        throw new InvalidArgumentException("Item distribusi tidak valid: {$item['id']}.");
                    }
                    $approvedQuantity = (int) $item['approved_quantity'];
                    $batch = $distributionItem->batch;

                    if (! $batch) {
                        throw new InvalidArgumentException('Batch tidak ditemukan.');
                    }


                    if ($approvedQuantity > $batch->current_quantity) {
                        throw new InvalidArgumentException(
                            "Stok batch {$batch->batch_code} tidak cukup. " .
                                "Tersedia: {$batch->current_quantity}, diminta: {$approvedQuantity}."
                        );
                    }
                    if ($approvedQuantity > $distributionItem->requested_quantity) {
                        throw new InvalidArgumentException('Approved quantity tidak boleh lebih besar dari requested quantity.');
                    }

                    $distributionItem->update([
                        'approved_quantity' => $approvedQuantity,
                    ]);
                }
            }

            if ($confirmedBy) {
                $distribution->confirmed_by = $confirmedBy;
            }

            if ($notes !== null) {
                $distribution->notes = $notes;
            }

            if ($newStatus === DistributionStatus::SHIPPED) {
                $distribution->dispatched_at = now();
            }

            if ($newStatus === DistributionStatus::COMPLETED) {
                if (is_array($items) && count($items) > 0) {
                    $distributionItems = $distribution->items()->get()->keyBy('id');
                    foreach ($items as $item) {
                        $distributionItem = $distributionItems[$item['id']] ?? null;
                        if (! $distributionItem) {
                            throw new InvalidArgumentException("Item distribusi tidak valid: {$item['id']}.");
                        }

                        $receivedQty = (int) ($item['received_quantity'] ?? 0);
                        $damagedQty = (int) ($item['damaged_quantity'] ?? 0);

                        if (($receivedQty + $damagedQty) !== $distributionItem->approved_quantity) {
                            throw new InvalidArgumentException(
                                "Total barang diterima ({$receivedQty}) dan rusak ({$damagedQty}) harus sama dengan jumlah yang dikirim ({$distributionItem->approved_quantity})."
                            );
                        }

                        $distributionItem->update([
                            'received_quantity' => $receivedQty,
                            'damaged_quantity' => $damagedQty,
                        ]);

                        if ($damagedQty > 0) {
                            $batch = $distributionItem->batch;
                            StockMutations::record(
                                $batch->warehouse_id,
                                $batch->id,
                                $batch->current_quantity,
                                0, // Tidak mengurangi ulang karena stok fisik sudah berkurang saat SHIPPED
                                MutationStatus::DAMAGED_IN_TRANSIT,
                                'DISTRIBUTION',
                                $distribution->id,
                                "Barang rusak saat perjalanan sebanyak {$damagedQty}"
                            );

                            // Automatic Return / Disposal
                            $isReturn = false;
                            if ($batch->receiving_id) {
                                $receiving = \App\Models\ProductReceiving::find($batch->receiving_id);
                                if ($receiving && in_array($receiving->receivable_type, [\App\Models\PurchaseOrder::class, 'purchase_order'])) {
                                    $po = \App\Models\PurchaseOrder::find($receiving->receivable_id);
                                    if ($po && $po->supplier_id) {
                                        $supplierItem = \App\Models\ProductSupplierItem::where('product_id', $batch->product_id)
                                            ->where('supplier_id', $po->supplier_id)
                                            ->first();
                                        
                                        if ($supplierItem && $supplierItem->return_limit_days > 0) {
                                            $limitDate = \Carbon\Carbon::parse($receiving->receiving_date)->addDays($supplierItem->return_limit_days);
                                            if (now()->lessThanOrEqualTo($limitDate)) {
                                                $isReturn = true;
                                            }
                                        }
                                    }
                                }
                            }

                            if ($isReturn) {
                                $stockReturn = \App\Models\StockReturns::create([
                                    'return_code' => 'RET-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                                    'receiving_id' => $batch->receiving_id,
                                    'product_id' => $batch->product_id,
                                    'warehouse_id' => $batch->warehouse_id,
                                    'requested_quantity' => $damagedQty,
                                    'approved_quantity' => 0,
                                    'reason' => "Otomatis dari Distribusi {$distribution->distribution_code} karena rusak di jalan",
                                    'requested_by' => $confirmedBy ?? \Illuminate\Support\Facades\Auth::id(),
                                    'status' => 'requested',
                                ]);

                                $superAdmins = User::whereHas('roles', function($q) {
                                    $q->where('name', 'super-admin');
                                })->get();
                                if ($superAdmins->isNotEmpty()) {
                                    Notification::send(
                                        $superAdmins,
                                        new ReturnNotification(
                                            $stockReturn,
                                            'Persetujuan Retur Baru',
                                            "Terdapat retur baru secara otomatis akibat barang rusak dalam perjalanan."
                                        )
                                    );
                                }
                            } else {
                                $stockDisposal = \App\Models\StockDisposal::create([
                                    'disposal_code' => 'DSP-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                                    'batch_id' => $batch->id,
                                    'product_id' => $batch->product_id,
                                    'warehouse_id' => $batch->warehouse_id,
                                    'requested_quantity' => $damagedQty,
                                    'approved_quantity' => 0,
                                    'reason' => "Otomatis dari Distribusi {$distribution->distribution_code} karena rusak di jalan",
                                    'requested_by' => $confirmedBy ?? \Illuminate\Support\Facades\Auth::id(),
                                    'status' => 'requested',
                                ]);

                                $superAdmins = User::whereHas('roles', function($q) {
                                    $q->where('name', 'super-admin');
                                })->get();
                                if ($superAdmins->isNotEmpty()) {
                                    Notification::send(
                                        $superAdmins,
                                        new DisposalNotification(
                                            $stockDisposal,
                                            'Persetujuan Pemusnahan Baru',
                                            "Terdapat pemusnahan baru secara otomatis akibat barang rusak dalam perjalanan."
                                        )
                                    );
                                }
                            }
                        }
                    }

                    $totalDamaged = collect($items)->sum(function($item) {
                        return (int) ($item['damaged_quantity'] ?? 0);
                    });

                    if ($totalDamaged > 0 && $distribution->request) {
                        Notification::send(
                            [$distribution->request],
                            new DistributionReplacementNotification(
                                $distribution,
                                $totalDamaged,
                                'Barang Rusak dalam Pengiriman',
                                "Beberapa barang rusak dalam proses Distribusi {$distribution->distribution_code}."
                            )
                        );
                    }
                }
                
                $distribution->delivered_at = now();
            }

            $distribution->status = $newStatus;
            $distribution->save();

            if ($newStatus === DistributionStatus::SHIPPED) {
                $this->applyStockMutation($distribution);
            }

            return $distribution->refresh()->load('items.batch', 'warehouse', 'request', 'confirmedBy');
        });
    }

    public function deleteDistribution(StockDistributions $distribution): bool
    {
        return DB::transaction(function () use ($distribution) {
            if (! in_array($distribution->status, [
                DistributionStatus::DRAFT,
                DistributionStatus::WAITING_APPROVAL,
                DistributionStatus::REJECTED,
                DistributionStatus::CANCELED,
            ], true)) {
                return false;
            }

            $distribution->items()->delete();

            return $distribution->delete();
        });
    }

    private function applyStockMutation(StockDistributions $distribution): void
    {
        $distribution->loadMissing('items.batch');

        foreach ($distribution->items as $item) {
            $batch = $item->batch;
            $batch->refresh(); // reload latest quantity to avoid race condition

            if (! $batch) {
                throw new ModelNotFoundException('Batch tidak ditemukan untuk item distribusi.');
            }

            $quantity = $item->approved_quantity > 0 ? $item->approved_quantity : $item->requested_quantity;

            if ($batch->current_quantity < $quantity) {
                throw new InvalidArgumentException('Stok batch tidak cukup untuk menyelesaikan distribusi.');
            }

            $before = $batch->current_quantity;
            $batch->decrement('current_quantity', $quantity);

            StockMutations::record(
                $batch->warehouse_id,
                $batch->id,
                $before,
                -$quantity,
                MutationStatus::DISTRIBUTION_COMPLETED,
                'DISTRIBUTION',
                $distribution->id,
                'Distribusi selesai ke ' . ($distribution->store?->name ?? 'toko')
            );
            
            $this->batchService->releaseLocation($batch, $quantity);
        }
    }
}
