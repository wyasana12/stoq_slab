<?php

namespace App\Services;

use App\Enums\DistributionStatus;
use App\Models\StockDistributions;
use App\Models\User;
use App\Notifications\DistributionNotification;
use Illuminate\Support\Facades\Notification;

class DistributionNotificationService
{
    public function sendDistributionNotification(StockDistributions $distribution, DistributionStatus $newStatus): void
    {
        // Pastikan relasi yang dibutuhkan dimuat
        $distribution->loadMissing(['warehouse.staffs', 'request']);

        $notificationData = match ($newStatus) {
            DistributionStatus::WAITING_APPROVAL => [
                // Saat menunggu persetujuan (biasanya Tarik/Pull), kirim ke staff gudang pusat
                'recipients' => $distribution->warehouse->staffs->unique('id'),
                'title' => 'Pengajuan Distribusi (Menunggu Persetujuan)',
                'message' => "Distribusi {$distribution->distribution_code} menunggu persetujuan dari Gudang Pusat.",
                'type' => 'info',
                'sendEmail' => true,
            ],
            DistributionStatus::APPROVED => [
                'recipients' => collect([$distribution->request]),
                'title' => 'Distribusi Disetujui',
                'message' => "Pengajuan Distribusi {$distribution->distribution_code} telah disetujui.",
                'type' => 'success',
                'sendEmail' => false,
            ],
            DistributionStatus::PREPARING => [
                'recipients' => collect([$distribution->request]),
                'title' => 'Distribusi Sedang Disiapkan',
                'message' => "Barang untuk Distribusi {$distribution->distribution_code} sedang disiapkan di gudang.",
                'type' => 'info',
                'sendEmail' => false,
            ],
            DistributionStatus::SHIPPED => [
                'recipients' => collect([$distribution->request]),
                'title' => 'Distribusi Dalam Pengiriman',
                'message' => "Barang untuk Distribusi {$distribution->distribution_code} sedang dikirim ke {$distribution->store->name}.",
                'type' => 'info',
                'sendEmail' => false,
            ],
            DistributionStatus::COMPLETED => [
                'recipients' => $distribution->warehouse->staffs->merge([$distribution->request])->unique('id'),
                'title' => 'Distribusi Selesai',
                'message' => "Proses Distribusi {$distribution->distribution_code} telah selesai sepenuhnya.",
                'type' => 'success',
                'sendEmail' => false, // Default completed admin gets no email. If damaged, a separate notification with email handles it.
            ],
            DistributionStatus::REJECTED => [
                'recipients' => collect([$distribution->request]),
                'title' => 'Pengajuan Distribusi Ditolak',
                'message' => "Distribusi {$distribution->distribution_code} telah ditolak.",
                'type' => 'error',
                'sendEmail' => true,
            ],
            DistributionStatus::CANCELED => [
                'recipients' => $distribution->warehouse->staffs->merge([$distribution->request])->unique('id'),
                'title' => 'Distribusi Dibatalkan',
                'message' => "Distribusi {$distribution->distribution_code} telah dibatalkan.",
                'type' => 'error',
                'sendEmail' => true,
            ],
            default => null,
        };

        if ($notificationData && $notificationData['recipients'] && $notificationData['recipients']->isNotEmpty()) {
            Notification::send(
                $notificationData['recipients']->filter(),
                new DistributionNotification(
                    $distribution,
                    $notificationData['title'],
                    $notificationData['message'],
                    $notificationData['type'] ?? 'info',
                    $notificationData['sendEmail'] ?? true
                )
            );
        }
    }
}
