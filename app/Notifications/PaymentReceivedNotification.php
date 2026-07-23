<?php

namespace App\Notifications;

use App\Models\ServiceOrder;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer saat webhook Xendit melaporkan pembayaran sukses
 * (lihat App\Http\Controllers\Webhooks\XenditWebhookController). Tanpa
 * channel mail — users.email masih placeholder inert untuk semua role,
 * lihat CLAUDE.md "Alur Bisnis Siklus Hidup Service".
 */
class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ServiceOrder $serviceOrder) {}

    public function via(object $notifiable): array
    {
        return ['database', WhatsappChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pembayaran Diterima',
            'message' => "Pembayaran untuk tagihan {$this->serviceOrder->code} sudah kami terima. Teknisi akan segera menghubungi Anda untuk jadwal pemasangan.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Pembayaran untuk tagihan {$this->serviceOrder->code} sudah kami terima. Terima kasih! Teknisi kami akan segera menghubungi Anda untuk jadwal pemasangan.";
    }
}
