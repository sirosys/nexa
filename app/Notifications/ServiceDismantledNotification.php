<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer begitu dismantle selesai dan Service mencapai status
 * akhir dismantled — lihat CLAUDE.md "Dismantle". Dipicu dari
 * DismantleService::complete().
 */
class ServiceDismantledNotification extends Notification implements ShouldQueue
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
            'title' => 'Layanan Dibongkar',
            'message' => "Layanan Anda ({$this->service->code}) telah selesai dibongkar.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Layanan Anda ({$this->service->code}) telah selesai dibongkar. Terima kasih telah menggunakan layanan kami.";
    }
}
