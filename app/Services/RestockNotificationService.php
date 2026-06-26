<?php

namespace App\Services;

use App\Enums\RestockStatus;
use App\Models\Restock;
use App\Models\User;
use App\Notifications\RestockNotification;
use Illuminate\Support\Facades\Notification;

class RestockNotificationService
{
    public function sendRestockNotification(Restock $restock, RestockStatus $newStatus): void
    {
        $restock->loadMissing(['warehouse.admins', 'request']);

        $notificationData = match ($newStatus) {
            RestockStatus::REQUESTED => [
                'recipients' => User::role('super-admin')->get(),
                'title' => 'Pengajuan Restock Baru',
                'message' => "Pengajuan Restock {$restock->restock_code} telah dibuat. Butuh peninjauan segera.",
                'type' => 'warning',
            ],
            RestockStatus::APPROVED => [
                'recipients' => collect([$restock->request]),
                'title' => 'Pengajuan Restock Disetujui',
                'message' => "Restock {$restock->restock_code} telah disetujui dan dilanjutkan ke pengiriman.",
                'type' => 'success',
            ],
            RestockStatus::ON_DELIVERY => [
                'recipients' => $restock->warehouse->admins,
                'title' => 'Restock Dalam Pengiriman',
                'message' => "Barang untuk Restock {$restock->restock_code} sedang dikirim ke gudang {$restock->warehouse->name}.",
                'type' => 'info',
            ],
            RestockStatus::REJECTED => [
                'recipients' => collect([$restock->request]),
                'title' => 'Restock Ditolak',
                'message' => "Restock {$restock->restock_code} telah ditolak. Alasan: " . ($restock->notes ?? 'Tidak ada keterangan tambahan.'),
                'type' => 'error',
            ],
            RestockStatus::COMPLETED => [
                'recipients' => $restock->warehouse->admins->merge([$restock->request])->unique('id'),
                'title' => 'Restock Selesai',
                'message' => "Seluruh barang Restock {$restock->restock_code} terkonfirmasi telah masuk ke gudang.",
                'type' => 'success',
            ],
            default => null,
        };

        if ($notificationData && $notificationData['recipients'] && $notificationData['recipients']->isNotEmpty()) {
            Notification::send(
                $notificationData['recipients']->filter(),
                new RestockNotification(
                    $restock,
                    $notificationData['title'],
                    $notificationData['message'],
                    $notificationData['type'] ?? 'info'
                )
            );
        }
    }
}
