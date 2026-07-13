<?php

namespace App\Services;

use App\Models\AlertConfig;
use App\Models\AlertLog;
use App\Models\Batch;
use App\Models\User;
use App\Notifications\BatchExpiryNotification;
use Illuminate\Support\Facades\Notification;

class AlertStockService
{
    /**
     * Jalankan pengecekan alert untuk semua konfigurasi yang aktif.
     * Mengecek dua kondisi:
     *   1. Batch mendekati kadaluarsa (berdasarkan days_before dari AlertConfig)
     *   2. Stok quantity mendekati habis (≤ 20% dari initial_quantity)
     *
     * @param string|null $warehouseId  Filter gudang tertentu (opsional)
     * @return array  Ringkasan hasil pengecekan
     */
    public function checkAndSendAlerts(?string $warehouseId = null): array
    {
        $activeConfigs = AlertConfig::where('is_active', true)->orderBy('days_before', 'desc')->get();

        if ($activeConfigs->isEmpty()) {
            return ['message' => 'Tidak ada konfigurasi alert yang aktif.', 'triggered' => 0];
        }

        $triggered = 0;
        $today = now()->startOfDay();

        // --- Cek 1: Batch mendekati kadaluarsa ---
        foreach ($activeConfigs as $config) {
            $thresholdDate = $today->copy()->addDays($config->days_before);

            $query = Batch::with(['product', 'warehouse'])
                ->where('current_quantity', '>', 0)
                ->whereIn('condition', ['BAIK', 'MENDEKATI_KADALUARSA'])
                ->whereDate('expired_date', '<=', $thresholdDate)
                ->whereDate('expired_date', '>=', $today);

            if ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            }

            $expiringBatches = $query->get();

            foreach ($expiringBatches as $batch) {
                // Hindari duplikat log dalam 1 hari
                $alreadyLogged = AlertLog::where('batch_id', $batch->id)
                    ->where('alert_config_id', $config->id)
                    ->whereDate('created_at', $today)
                    ->exists();

                if ($alreadyLogged) {
                    continue;
                }

                $daysLeft = $today->diffInDays($batch->expired_date, false);
                $daysLabel = $daysLeft <= 0 ? 'sudah kadaluarsa' : "tersisa {$daysLeft} hari";

                $title   = "⚠️ Batch Mendekati Kadaluarsa";
                $message = "Batch {$batch->batch_code} — {$batch->product?->name} "
                    . "di Gudang {$batch->warehouse?->name} "
                    . "kadaluarsa pada " . $batch->expired_date->format('d M Y') . " ({$daysLabel}). "
                    . "Stok tersisa: {$batch->current_quantity}.";

                AlertLog::create([
                    'warehouse_id'    => $batch->warehouse_id,
                    'batch_id'        => $batch->id,
                    'alert_config_id' => $config->id,
                    'title'           => $title,
                    'message'         => $message,
                ]);

                $this->sendNotification($batch, $title, $message);
                $triggered++;
            }
        }

        // --- Cek 2: Stok mendekati habis (≤ 20% dari initial_quantity) ---
        $lowStockQuery = Batch::with(['product', 'warehouse'])
            ->whereColumn('current_quantity', '<=', \Illuminate\Support\Facades\DB::raw('initial_quantity * 0.2'))
            ->where('initial_quantity', '>', 0)
            ->where('current_quantity', '>', 0)
            ->whereIn('condition', ['BAIK', 'MENDEKATI_KADALUARSA']);

        if ($warehouseId) {
            $lowStockQuery->where('warehouse_id', $warehouseId);
        }

        $lowStockBatches = $lowStockQuery->get();

        foreach ($lowStockBatches as $batch) {
            // Gunakan config dengan days_before terkecil sebagai referensi (atau config pertama)
            $config = $activeConfigs->first();

            // Hindari duplikat log dalam 1 hari
            $alreadyLogged = AlertLog::where('batch_id', $batch->id)
                ->where('alert_config_id', $config?->id)
                ->whereDate('created_at', $today)
                ->where('title', 'like', '%Stok Menipis%')
                ->exists();

            if ($alreadyLogged) {
                continue;
            }

            $percentage = $batch->initial_quantity > 0
                ? round(($batch->current_quantity / $batch->initial_quantity) * 100, 1)
                : 0;

            $title   = "📦 Stok Menipis";
            $message = "Stok batch {$batch->batch_code} — {$batch->product?->name} "
                . "di Gudang {$batch->warehouse?->name} "
                . "hanya tersisa {$batch->current_quantity} ({$percentage}% dari stok awal {$batch->initial_quantity}). "
                . "Segera lakukan restock atau transfer.";

            AlertLog::create([
                'warehouse_id'    => $batch->warehouse_id,
                'batch_id'        => $batch->id,
                'alert_config_id' => $config?->id ?? $activeConfigs->first()->id,
                'title'           => $title,
                'message'         => $message,
            ]);

            $this->sendNotification($batch, $title, $message);
            $triggered++;
        }

        return [
            'message'   => "Pengecekan selesai. {$triggered} alert berhasil dikirim.",
            'triggered' => $triggered,
        ];
    }

    /**
     * Kirim notifikasi ke superadmin dan admin gudang terkait.
     */
    private function sendNotification(Batch $batch, string $title, string $message): void
    {
        $superAdmins   = User::role('super-admin')->get();
        $warehouseAdmins = $batch->warehouse?->admins ?? collect();

        $recipients = $superAdmins->merge($warehouseAdmins)->unique('id')->filter();

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new BatchExpiryNotification($batch, $title, $message)
            );
        }
    }
}
