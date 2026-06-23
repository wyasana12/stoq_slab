<?php

namespace App\Notifications;

use App\Models\StockTransfers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class TransferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockTransfers $transfer,
        public string $title,
        public string $message,
        public string $type = 'info'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'transfer_id' => $this->transfer->id,
            'transfer_code' => $this->transfer->transfer_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->transfer->status->value ?? null,
            'type' => $this->type,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'transfer_id' => $this->transfer->id,
            'transfer_code' => $this->transfer->transfer_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->transfer->status->value ?? null,
            'type' => $this->type,
            'created_at' => now()->toDateTimeString()
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
