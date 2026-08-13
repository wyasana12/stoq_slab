<?php

namespace App\Notifications;

use App\Models\StockDistributions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DistributionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public StockDistributions $distribution,
        public string $title,
        public string $message,
        public string $type = 'info',
        public bool $sendEmail = true
    ) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];
        if ($this->sendEmail) {
            $channels[] = 'mail';
        }
        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $statusStr = $this->distribution->status instanceof \BackedEnum 
            ? $this->distribution->status->value 
            : $this->distribution->status;
            
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('Halo, ')
            ->line($this->message)
            ->line("Kode Distribusi: **{$this->distribution->distribution_code}**")
            ->line("Status saat ini: **" . strtoupper($statusStr) . "**")
            ->action('Notification Action', $this->getActionUrl($notifiable))
            ->line('Notifikasi ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.');
    }

    public function toDatabase(object $notifiable): array
    {
        $statusStr = $this->distribution->status instanceof \BackedEnum 
            ? $this->distribution->status->value 
            : $this->distribution->status;

        return [
            'distribution_id' => $this->distribution->id,
            'distribution_code' => $this->distribution->distribution_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $statusStr,
            'type' => $this->type,
            'action_url' => $this->getActionUrl($notifiable),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $statusStr = $this->distribution->status instanceof \BackedEnum 
            ? $this->distribution->status->value 
            : $this->distribution->status;

        return new BroadcastMessage([
            'distribution_id' => $this->distribution->id,
            'distribution_code' => $this->distribution->distribution_code ?? null,
            'title' => $this->title,
            'message' => $this->message,
            'status' => $statusStr,
            'type' => $this->type,
            'action_url' => $this->getActionUrl($notifiable),
            'created_at' => now()->toDateTimeString()
        ]);
    }

    protected function getActionUrl(object $notifiable): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8080');

        if ($notifiable->hasRole('staff')) {
            return url("{$frontendUrl}/distribusikeswalayan-staff?distribution_id={$this->distribution->id}&action=detail");
        }

        return url("{$frontendUrl}/distribusikeswalayan?distribution_id={$this->distribution->id}&action=detail");
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
