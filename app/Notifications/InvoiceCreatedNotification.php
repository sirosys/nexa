<?php

namespace App\Notifications;

use App\Models\Receipt;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer begitu tagihan (Order Layanan + Payment Request
 * Xendit) berhasil dibuat — lihat CLAUDE.md "Billing / Invoice (Xendit)".
 * Dipicu dari ReceiptService::createForServiceOrder() (bukan ServiceService)
 * supaya retry manual (ServiceOrderController::retryReceipt()) juga ikut
 * memicu notifikasi ini, bukan cuma percobaan pertama.
 *
 * Dipakai juga oleh modul Renewal untuk tagihan perpanjangan yang dibuat
 * otomatis di H-5 — notifikasi ini SEKALIGUS jadi reminder H-5 (tidak ada
 * notifikasi H-5 terpisah, lihat CLAUDE.md "Renewal"). Wording "pendaftaran"
 * vs "perpanjangan" dibedakan lewat $receipt->serviceOrder->is_renewal.
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
            'message' => "Tagihan {$this->label()} {$this->receipt->serviceOrder->code} sebesar {$this->formattedAmount()} sudah dibuat.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Halo {$notifiable->name}, tagihan {$this->label()} Anda ({$this->receipt->serviceOrder->code}) sebesar {$this->formattedAmount()} sudah dibuat. Silakan bayar melalui link berikut: {$this->receipt->checkout_url}";
    }

    private function label(): string
    {
        return $this->receipt->serviceOrder->is_renewal ? 'perpanjangan' : 'pendaftaran';
    }

    private function formattedAmount(): string
    {
        return 'Rp'.number_format((float) $this->receipt->amount, 0, ',', '.');
    }
}
