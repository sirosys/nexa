<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke teknisi begitu sebuah Service ditugaskan/diklaim untuknya —
 * lihat CLAUDE.md "Installation". Dipicu dari InstallationService::assign()
 * dan ::claim().
 */
class TechnicianAssignedNotification extends Notification implements ShouldQueue
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
            'title' => 'Job Instalasi Baru',
            'message' => "Anda ditugaskan memasang layanan {$this->service->code} di {$this->service->address}.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $this->service->loadMissing('user');

        $customer = $this->service->user;

        return "[NEXA] Anda ditugaskan memasang layanan {$this->service->code} di {$this->service->address}, milik {$customer?->name} ({$customer?->phone}).";
    }
}
