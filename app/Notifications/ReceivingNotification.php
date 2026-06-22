<?php

namespace App\Notifications;

use App\Models\ProductReceiving;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReceivingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ProductReceiving $receiving,
        public string $title,
        public string $message,
        public string $type = 'info'
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    protected function getActionUrl(object $notifiable): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8080');

        if ($notifiable->hasRole(['admin'])) {
            return url("{$frontendUrl}/purchaseorder?action=open_form");
        }

        return url("{$frontendUrl}/kondisilokasirak");
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Halo, ')
            ->line($this->message)
            ->line("Kode Receiving: **{$this->receiving->receiving_code}**")
            ->action('Notification Action', $this->getActionUrl($notifiable))
            ->line('Notifikasi ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'receiving_id' => $this->receiving->id,
            'receiving_code' => $this->receiving->receiving_code,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->receiving->status->value ?? null,
            'type' => $this->type,
                        'action_url' => $this->getActionUrl($notifiable),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'receiving_id' => $this->receiving->id,
            'receiving_code' => $this->receiving->receiving_code,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->receiving->status->value ?? null,
            'type' => $this->type,
                        'action_url' => $this->getActionUrl($notifiable),
            'created_at' => now()->toDateTimeString()
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
            //
        ];
    }
}
