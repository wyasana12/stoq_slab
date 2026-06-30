<?php

namespace App\Notifications;

use App\Models\StockDisposal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DisposalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockDisposal $stockDisposal,
        public string $title,
        public string $message,
        public string $type = 'info'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Halo, ')
            ->line($this->message)
            ->line("Kode Pemusnahan: **{$this->stockDisposal->disposal_code}**")
            ->line("Status saat ini: **" . strtoupper($this->stockDisposal->status) . "**")
            ->action('Lihat Pengajuan', $this->getActionUrl($notifiable))
            ->line('Notifikasi ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'disposal_id' => $this->stockDisposal->id,
            'disposal_code' => $this->stockDisposal->disposal_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->stockDisposal->status,
            'type' => $this->type,
            'action_url' => $this->getActionUrl($notifiable),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'disposal_id' => $this->stockDisposal->id,
            'disposal_code' => $this->stockDisposal->disposal_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->stockDisposal->status,
            'type' => $this->type,
            'action_url' => $this->getActionUrl($notifiable),
            'created_at' => now()->toDateTimeString()
        ]);
    }

    protected function getActionUrl(?object $notifiable = null): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8080');

        if ($notifiable && method_exists($notifiable, 'hasRole') && $notifiable->hasRole('super-admin')) {
            return url("{$frontendUrl}/konfirmasipemusnahan");
        }

        return url("{$frontendUrl}/pemusnahan");
    }
}
