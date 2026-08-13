<?php

namespace App\Notifications;

use App\Models\StockReturns;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ReturnNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockReturns $stockReturn,
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
            ->line("Kode Retur: **{$this->stockReturn->return_code}**")
            ->line("Status saat ini: **" . strtoupper($this->stockReturn->status) . "**")
            ->action('Lihat Pengajuan', $this->getActionUrl())
            ->line('Notifikasi ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'return_id' => $this->stockReturn->id,
            'return_code' => $this->stockReturn->return_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->stockReturn->status,
            'type' => $this->type,
            'action_url' => $this->getActionUrl(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'return_id' => $this->stockReturn->id,
            'return_code' => $this->stockReturn->return_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $this->stockReturn->status,
            'type' => $this->type,
            'action_url' => $this->getActionUrl(),
            'created_at' => now()->toDateTimeString()
        ]);
    }

    protected function getActionUrl(): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8080');
        return url("{$frontendUrl}/konfirmasikondisibarang?tab=0&return_id={$this->stockReturn->id}&action=detail");
    }
}