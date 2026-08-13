<?php

namespace App\Notifications;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public PurchaseOrder $purchase,
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

    protected function getActionUrl(): string
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:8080');

        return match ($this->purchase->status) {
            PurchaseOrderStatus::SUBMITTED => url("{$frontendUrl}/konfirmasipo?action=open&po={$this->purchase->id}"),
            PurchaseOrderStatus::ORDERED => url("{$frontendUrl}/terima?action=open&doc_type=purchase_order&doc_id={$this->purchase->id}"),
            default => url("{$frontendUrl}/purchaseorder?action=open&po={$this->purchase->id}")
        };
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
            ->line("Kode PO: **{$this->purchase->po_code}**")
            ->line("Status saat ini: **" . strtoupper($this->purchase->status->value) . "**")
            ->action('Notification Action', $this->getActionUrl())
            ->line('Notifikasi ini dikirim secara otomatis oleh sistem. Mohon tidak membalas email ini.');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'purchase_order_id' => $this->purchase->id,
            'po_code'           => $this->purchase->po_code ?? null,
            'title'             => $this->title,
            'message'           => $this->message,
            'status'            => $this->purchase->status->value ?? null,
            'type'              => $this->type,
            'action_url' => $this->getActionUrl(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'purchase_order_id' => $this->purchase->id,
            'po_code'           => $this->purchase->po_code ?? null,
            'title'             => $this->title,
            'message'           => $this->message,
            'status'            => $this->purchase->status->value ?? null,
            'type' => $this->type,
            'action_url' => $this->getActionUrl(),
            'created_at'              => now()->toDateTimeString()
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
