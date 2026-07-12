<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer begitu pembayaran perpanjangan diterima dan Service
 * aktif kembali — lihat CLAUDE.md "Renewal". Dipicu dari
 * RenewalService::reactivate() (dipanggil dari XenditWebhookController).
 *
 * Sengaja TIDAK reuse ServiceActivatedNotification — wording-nya spesifik
 * untuk instalasi baru selesai ("layanan Anda sudah aktif per hari ini"),
 * tidak pas untuk reaktivasi/perpanjangan (tidak ada teknisi yang datang).
 */
class ServiceReactivatedNotification extends Notification implements ShouldQueue
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
            'title' => 'Layanan Aktif Kembali',
            'message' => "Pembayaran perpanjangan layanan Anda ({$this->service->code}) sudah kami terima. Layanan aktif kembali, berlaku hingga {$this->service->expired_at?->translatedFormat('d F Y')}.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Terima kasih {$notifiable->name}, pembayaran perpanjangan layanan Anda ({$this->service->code}) sudah kami terima. Layanan aktif kembali, berlaku hingga {$this->service->expired_at?->translatedFormat('d F Y')}.";
    }
}
