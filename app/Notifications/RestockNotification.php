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
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): \Illuminate\Notifications\Messages\MailMessage
    {
        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject($this->title)
            ->greeting('Halo, ')
            ->line($this->message)
            ->line("Kode Restock: **{$this->restock->restock_code}**")
            ->line("Status saat ini: **" . strtoupper($this->restock->status->value ?? 'N/A') . "**")
            ->action('Lihat Detail', $this->getActionUrl($notifiable))
            ->line('Notifikasi ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.');
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
            'action_url' => $this->getActionUrl($notifiable),
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
            'action_url' => $this->getActionUrl($notifiable),
            'created_at' => now()->toDateTimeString()
        ]);
    }

    protected function getActionUrl(object $notifiable): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8080');

        if ($notifiable->hasRole('super-admin')) {
            return url("{$frontendUrl}/konfirmasirestock");
        }

        return url("{$frontendUrl}/restocks");
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
