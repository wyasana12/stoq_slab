<?php

namespace App\Notifications;

use App\Models\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class BatchExpiryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Batch $batch,
        public string $title,
        public string $message,
        public string $type = 'warning'
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'batch_id'     => $this->batch->id,
            'batch_code'   => $this->batch->batch_code ?? null,
            'product_name' => $this->batch->product?->name ?? null,
            'warehouse'    => $this->batch->warehouse?->name ?? null,
            'title'        => $this->title,
            'message'      => $this->message,
            'type'         => $this->type,
        ];
    }

    /**
     * Get the broadcast representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'batch_id'     => $this->batch->id,
            'batch_code'   => $this->batch->batch_code ?? null,
            'product_name' => $this->batch->product?->name ?? null,
            'warehouse'    => $this->batch->warehouse?->name ?? null,
            'title'        => $this->title,
            'message'      => $this->message,
            'type'         => $this->type,
            'created_at'   => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title'   => $this->title,
            'message' => $this->message,
        ];
    }
}

