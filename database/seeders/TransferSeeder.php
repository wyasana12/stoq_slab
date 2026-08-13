<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\StockMutations;
use App\Models\StockTransfers;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Illuminate\Support\now;

class TransferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first();
        if (! $user) {
            return;
        }

        // create one OUT transfer: pick a batch with available qty and send to another warehouse
        $sourceBatch = Batch::where('current_quantity', '>', 0)->first();
        if ($sourceBatch) {
            $fromWarehouse = Warehouse::find($sourceBatch->warehouse_id);
            $toWarehouse = Warehouse::where('id', '!=', $fromWarehouse->id)->first();

            if ($fromWarehouse && $toWarehouse) {
                $available = (int) $sourceBatch->current_quantity;
                $qty = rand(1, max(1, min(10, $available)));

                DB::transaction(function () use ($sourceBatch, $fromWarehouse, $toWarehouse, $user, $qty) {
                    $transferOut = StockTransfers::create([
                        'transfer_code' => 'TF-' . now()->format('Ymd') . '-' . rand(1000, 9999),
                        'transfer_type' => 'out',
                        'from_warehouse_id' => $fromWarehouse->id,
                        'to_warehouse_id' => $toWarehouse->id,
                        'product_id' => $sourceBatch->product_id,
                        'requested_quantity' => $qty,
                        'approved_quantity' => $qty,
                        'requested_by' => $user->id,
                        'confirmed_by' => $user->id,
                        'notes' => 'Seeder OUT transfer',
                        'status' => 'COMPLETED',
                    ]);

                    $before = (int) $sourceBatch->current_quantity;
                    $decrement = min($qty, $before);
                    if ($decrement > 0) {
                        $sourceBatch->decrement('current_quantity', $decrement);

                        StockMutations::create([
                            'warehouse_id' => $fromWarehouse->id,
                            'batch_id' => $sourceBatch->id,
                            'change_quantity' => -1 * $decrement,
                            'before_quantity' => $before,
                            'after_quantity' => $sourceBatch->current_quantity,
                            'reference_type' => 'TRANSFER',
                            'reference_id' => $transferOut->id,
                            'notes' => 'Seeder: send to ' . $toWarehouse->name,
                            'status' => 'COMPLETED',
                        ]);

                        // destination batch: try to find similar batch, otherwise create
                        $dest = Batch::query()
                            ->where('warehouse_id', $toWarehouse->id)
                            ->where('product_id', $sourceBatch->product_id)
                            ->where('production_date', $sourceBatch->production_date)
                            ->where('expired_date', $sourceBatch->expired_date)
                            ->first();

                        if (! $dest) {
                            $dest = Batch::create([
                                'batch_code' => 'BTCH-' . now()->format('Ymd') . '-' . rand(1000, 1999),
                                'product_id' => $sourceBatch->product_id,
                                'warehouse_id' => $toWarehouse->id,
                                'receiving_id' => $sourceBatch->receiving_id,
                                'production_date' => $sourceBatch->production_date,
                                'expired_date' => $sourceBatch->expired_date,
                                'initial_quantity' => $decrement,
                                'current_quantity' => $decrement,
                                'price' => $sourceBatch->price,
                                'condition' => $sourceBatch->condition,
                                'barcode' => $sourceBatch->barcode,
                            ]);
                        } else {
                            $destBefore = $dest->current_quantity;
                            $dest->increment('current_quantity', $decrement);
                        }

                        StockMutations::create([
                            'warehouse_id' => $toWarehouse->id,
                            'batch_id' => $dest->id,
                            'change_quantity' => $decrement,
                            'before_quantity' => $dest->initial_quantity ?? 0,
                            'after_quantity' => $dest->current_quantity,
                            'reference_type' => 'TRANSFER',
                            'reference_id' => $transferOut->id,
                            'notes' => 'Seeder: received from ' . $fromWarehouse->name,
                            'status' => 'SUCCESS',
                        ]);
                    }
                });
            }
        }

        // create one IN transfer: pick a different batch (preferably from another warehouse)
        $batchIn = Batch::where('current_quantity', '>', 0)
            ->when($sourceBatch, fn($q) => $q->where('id', '!=', $sourceBatch->id))
            ->first();

        if ($batchIn) {
            $fromW = Warehouse::find($batchIn->warehouse_id);
            $toW = Warehouse::where('id', '!=', $fromW->id)->first();

            if ($fromW && $toW) {
                $availableIn = (int) $batchIn->current_quantity;
                $qtyIn = rand(1, max(1, min(10, $availableIn)));

                DB::transaction(function () use ($batchIn, $fromW, $toW, $user, $qtyIn) {
                    $transferIn = StockTransfers::create([
                        'transfer_code' => 'TF-' . now()->format('Ymd') . '-' . rand(2000, 2999),
                        'transfer_type' => 'in',
                        'from_warehouse_id' => $toW->id,
                        'to_warehouse_id' => $fromW->id,
                        'product_id' => $batchIn->product_id,
                        'requested_quantity' => $qtyIn,
                        'approved_quantity' => $qtyIn,
                        'requested_by' => $user->id,
                        'confirmed_by' => $user->id,
                        'notes' => 'Seeder IN transfer',
                        'status' => 'COMPLETED',
                    ]);

                    $beforeIn = (int) $batchIn->current_quantity;
                    $decrementIn = min($qtyIn, $beforeIn);
                    if ($decrementIn > 0) {
                        $batchIn->decrement('current_quantity', $decrementIn);

                        StockMutations::create([
                            'warehouse_id' => $fromW->id,
                            'batch_id' => $batchIn->id,
                            'change_quantity' => -1 * $decrementIn,
                            'before_quantity' => $beforeIn,
                            'after_quantity' => $batchIn->current_quantity,
                            'reference_type' => 'TRANSFER',
                            'reference_id' => $transferIn->id,
                            'notes' => 'Seeder IN: send to ' . $toW->name,
                            'status' => 'SUCCESS',
                        ]);

                        $destIn = Batch::query()
                            ->where('warehouse_id', $toW->id)
                            ->where('product_id', $batchIn->product_id)
                            ->where('production_date', $batchIn->production_date)
                            ->where('expired_date', $batchIn->expired_date)
                            ->first();

                        if (! $destIn) {
                            $destIn = Batch::create([
                                'batch_code' => 'BTCH-' . now()->format('Ymd') . '-' . rand(3000, 3999),
                                'product_id' => $batchIn->product_id,
                                'warehouse_id' => $toW->id,
                                'receiving_id' => $batchIn->receiving_id,
                                'production_date' => $batchIn->production_date,
                                'expired_date' => $batchIn->expired_date,
                                'initial_quantity' => $decrementIn,
                                'current_quantity' => $decrementIn,
                                'price' => $batchIn->price,
                                'condition' => $batchIn->condition,
                                'barcode' => $batchIn->barcode,
                            ]);
                        } else {
                            $destIn->increment('current_quantity', $decrementIn);
                        }

                        StockMutations::create([
                            'warehouse_id' => $toW->id,
                            'batch_id' => $destIn->id,
                            'change_quantity' => $decrementIn,
                            'before_quantity' => $destIn->initial_quantity ?? 0,
                            'after_quantity' => $destIn->current_quantity,
                            'reference_type' => 'TRANSFER',
                            'reference_id' => $transferIn->id,
                            'notes' => 'Seeder IN: received from ' . $fromW->name,
                            'status' => 'SUCCESS',
                        ]);
                    }
                });
            }
        }
    }
}
