<?php

namespace App\Notifications;

use App\Models\ServiceOrder;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Reminder susulan H-3/H-1 untuk tagihan perpanjangan yang dibuat di H-5
 * (lihat InvoiceCreatedNotification, RenewalService::createInvoiceForDueService())
 * dan masih belum dibayar. Merujuk checkout_url yang SAMA dari H-5 — tidak
 * ada Order Layanan/Receipt baru untuk reminder ini. Lihat CLAUDE.md "Renewal".
 */
class RenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ServiceOrder $serviceOrder,
        private readonly int $daysRemaining,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WhatsappChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pengingat Perpanjangan',
            'message' => "Layanan Anda ({$this->serviceOrder->service->code}) akan berakhir dalam {$this->daysRemaining} hari. Tagihan perpanjangan {$this->serviceOrder->code} masih belum dibayar.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $expiredAt = $this->serviceOrder->service->expired_at?->translatedFormat('d F Y');

        return "[NEXA] Halo {$notifiable->name}, layanan Anda ({$this->serviceOrder->service->code}) akan berakhir dalam {$this->daysRemaining} hari (jatuh tempo {$expiredAt}). Segera bayar tagihan perpanjangan ({$this->serviceOrder->code}) melalui link berikut: {$this->serviceOrder->receipt?->checkout_url}";
    }
}
