<?php

namespace App\Notifications;

use App\Models\StockDistributions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DistributionReplacementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockDistributions $distribution,
        public int $damagedQuantity,
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
            ->line("Kode Distribusi Awal: **{$this->distribution->distribution_code}**")
            ->line("Jumlah Barang Rusak: **{$this->damagedQuantity} unit**")
            ->line("Mohon segera membuat pengajuan distribusi baru untuk menggantikan barang yang rusak tersebut.")
            ->action('Buat Distribusi Baru', $this->getActionUrl())
            ->line('Notifikasi ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'distribution_id' => $this->distribution->id,
            'distribution_code' => $this->distribution->distribution_code ?? null,
            'damaged_quantity' => $this->damagedQuantity,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'action_url' => $this->getActionUrl(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'distribution_id' => $this->distribution->id,
            'distribution_code' => $this->distribution->distribution_code ?? null,
            'damaged_quantity' => $this->damagedQuantity,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'action_url' => $this->getActionUrl(),
            'created_at' => now()->toDateTimeString()
        ]);
    }

    protected function getActionUrl(): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8080');
        return url("{$frontendUrl}/distribusikeswalayan?action=create&source_distribution_id={$this->distribution->id}");
    }
}
