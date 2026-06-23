<?php

namespace App\Notifications;

use App\Models\Restock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class RestockNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Restock $restock,
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
            'restock_id' => $this->restock->id,
            'restock_code' => $this->restock->restock_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->restock->status->value ?? null,
            'type' => $this->type,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'restock_id' => $this->restock->id,
            'restock_code' => $this->restock->restock_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->restock->status->value ?? null,
            'type' => $this->type,
            'created_at' => now()->toDateTimeString()
        ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
