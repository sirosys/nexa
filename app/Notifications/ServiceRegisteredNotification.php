<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer begitu Service baru berhasil didaftarkan — lihat
 * CLAUDE.md "Service". Dipicu dari ServiceService::create(), sebelum
 * ReceiptService membuat tagihan (jadi urutan notifikasi yang diterima
 * customer: "layanan terdaftar" dulu, baru "tagihan dibuat").
 */
class ServiceRegisteredNotification extends Notification implements ShouldQueue
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
            'title' => 'Layanan Terdaftar',
            'message' => "Layanan Anda ({$this->service->code}) di {$this->service->address} berhasil didaftarkan.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Halo {$notifiable->name}, layanan Anda ({$this->service->code}) di {$this->service->address} berhasil didaftarkan. Tagihan pendaftaran akan segera kami kirimkan.";
    }
}
