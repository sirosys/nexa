<?php

namespace App\Notifications;

use App\Models\Receipt;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer begitu tagihan pendaftaran (Sale + Payment Request
 * Xendit) berhasil dibuat — lihat CLAUDE.md "Billing / Invoice (Xendit)".
 * Dipicu dari ReceiptService::createForSale() (bukan ServiceService)
 * supaya retry manual (SaleController::retryReceipt()) juga ikut memicu
 * notifikasi ini, bukan cuma percobaan pertama.
 */
class InvoiceCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Receipt $receipt) {}

    public function via(object $notifiable): array
    {
        return ['database', WhatsappChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tagihan Baru',
            'message' => "Tagihan pendaftaran {$this->receipt->sale->code} sebesar {$this->formattedAmount()} sudah dibuat.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Halo {$notifiable->name}, tagihan pendaftaran Anda ({$this->receipt->sale->code}) sebesar {$this->formattedAmount()} sudah dibuat. Silakan bayar melalui link berikut: {$this->receipt->checkout_url}";
    }

    private function formattedAmount(): string
    {
        return 'Rp'.number_format((float) $this->receipt->amount, 0, ',', '.');
    }
}
