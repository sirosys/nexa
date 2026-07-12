<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer begitu instalasi selesai dan Service jadi aktif —
 * lihat CLAUDE.md "Installation". Dipicu dari InstallationService::complete().
 */
class ServiceActivatedNotification extends Notification implements ShouldQueue
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
            'title' => 'Layanan Aktif',
            'message' => "Layanan Anda ({$this->service->code}) sudah aktif, berlaku hingga {$this->service->expired_at?->translatedFormat('d F Y')}.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Selamat {$notifiable->name}, layanan Anda ({$this->service->code}) sudah aktif per hari ini, berlaku hingga {$this->service->expired_at?->translatedFormat('d F Y')}.";
    }
}
