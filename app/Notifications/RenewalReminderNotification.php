<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Reminder susulan H-3/H-1 untuk tagihan perpanjangan yang dibuat di H-5
 * (lihat InvoiceCreatedNotification, RenewalService::createInvoiceForDueService())
 * dan masih belum dibayar. Merujuk checkout_url yang SAMA dari H-5 — tidak
 * ada Sale/Receipt baru untuk reminder ini. Lihat CLAUDE.md "Renewal".
 */
class RenewalReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Sale $sale,
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
            'message' => "Layanan Anda ({$this->sale->service->code}) akan berakhir dalam {$this->daysRemaining} hari. Tagihan perpanjangan {$this->sale->code} masih belum dibayar.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $expiredAt = $this->sale->service->expired_at?->translatedFormat('d F Y');

        return "[NEXA] Halo {$notifiable->name}, layanan Anda ({$this->sale->service->code}) akan berakhir dalam {$this->daysRemaining} hari (jatuh tempo {$expiredAt}). Segera bayar tagihan perpanjangan ({$this->sale->code}) melalui link berikut: {$this->sale->receipt?->checkout_url}";
    }
}
