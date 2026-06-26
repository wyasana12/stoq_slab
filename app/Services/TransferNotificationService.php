<?php

namespace App\Services;

use App\Enums\TransferStatus;
use App\Models\StockTransfers;
use App\Models\User;
use App\Notifications\TransferNotification;
use Illuminate\Support\Facades\Notification;

class TransferNotificationService
{
    public function sendTransferNotification(StockTransfers $transfer, TransferStatus $newStatus): void
    {
        $transfer->loadMissing(['fromWarehouse.admins', 'toWarehouse.admins', 'request']);

        $notificationData = match ($newStatus) {
            TransferStatus::REQUESTED => [
                'recipients' => collect([$transfer->request]),
                'title' => 'Pengajuan Transfer (Requested)',
                'message' => "Transfer {$transfer->transfer_code} telah dibuat sebagai Requested.",
                'type' => 'info',
            ],
            TransferStatus::APPROVED => [
                'recipients' => collect([$transfer->request]),
                'title' => 'Pengajuan Transfer Disetujui',
                'message' => "Transfer {$transfer->transfer_code} telah disetujui. Siap untuk dikirim.",
                'type' => 'success',
            ],
            TransferStatus::ON_DELIVERY => [
                'recipients' => $transfer->toWarehouse->admins->merge([$transfer->request])->unique('id'),
                'title' => 'Transfer Dalam Pengiriman',
                'message' => "Barang untuk Transfer {$transfer->transfer_code} sedang dikirim ke gudang {$transfer->toWarehouse->name}.",
                'type' => 'info',
            ],
            TransferStatus::RECEIVED => [
                'recipients' => $transfer->fromWarehouse->admins->merge([$transfer->request])->unique('id'),
                'title' => 'Transfer Diterima',
                'message' => "Barang untuk Transfer {$transfer->transfer_code} telah tiba dan diterima di gudang tujuan.",
                'type' => 'success',
            ],
            TransferStatus::COMPLETED => [
                'recipients' => $transfer->toWarehouse->admins->merge($transfer->fromWarehouse->admins)->merge([$transfer->request])->unique('id'),
                'title' => 'Transfer Selesai',
                'message' => "Proses Transfer {$transfer->transfer_code} telah selesai sepenuhnya.",
                'type' => 'success',
            ],
            TransferStatus::REJECTED => [
                'recipients' => collect([$transfer->request]),
                'title' => 'Pengajuan Transfer Ditolak',
                'message' => "Transfer {$transfer->transfer_code} telah ditolak.",
                'type' => 'error',
            ],
            default => null,
        };

        if ($notificationData && $notificationData['recipients'] && $notificationData['recipients']->isNotEmpty()) {
            Notification::send(
                $notificationData['recipients']->filter(),
                new TransferNotification(
                    $transfer,
                    $notificationData['title'],
                    $notificationData['message'],
                    $notificationData['type'] ?? 'info'
                )
            );
        }
    }
}
