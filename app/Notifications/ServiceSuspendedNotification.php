<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer begitu Service disuspend karena tagihan perpanjangan
 * belum dibayar setelah lewat expired_at — lihat CLAUDE.md "Renewal".
 * Dipicu dari RenewalService::suspend() (command renewal:suspend-overdue).
 */
class ServiceSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Service $service) {}

    public function via(object $notifiable): array
    {
        return ['database', WhatsappChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Layanan Disuspend',
            'message' => "Layanan Anda ({$this->service->code}) disuspend karena tagihan perpanjangan belum dibayar. Layanan akan otomatis aktif kembali begitu pembayaran kami terima.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Mohon maaf {$notifiable->name}, layanan Anda ({$this->service->code}) telah disuspend karena tagihan perpanjangan belum dibayar. Layanan akan otomatis aktif kembali begitu pembayaran kami terima.";
    }
}
