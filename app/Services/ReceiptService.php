<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\Setting;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\Billing\XenditGateway;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use RuntimeException;

class ReceiptService
{
    public function __construct(
        private readonly XenditGateway $gateway,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Buat tagihan untuk sebuah Order Layanan. Kalau grandtotal 0 (paket
     * promo gratis), skip sepenuhnya dan langsung tandai lunas — tidak ada
     * gunanya membuat tagihan untuk Rp 0.
     *
     * Payment Requests API v3 Xendit TIDAK punya halaman checkout hosted
     * multi-channel (dikonfirmasi lewat percobaan langsung ke sandbox —
     * request tanpa channel_code ditolak), jadi method ini TIDAK memanggil
     * Xendit sama sekali. Yang dibuat di sini cuma Receipt berstatus
     * Receipt::STATUS_AWAITING_CHANNEL_SELECTION beserta link publik
     * (Laravel signed URL) ke halaman /pay/{receipt} milik NEXA sendiri,
     * tempat pelanggan memilih channel — panggilan Xendit sungguhan baru
     * terjadi di selectChannel() begitu pelanggan memilih.
     *
     * Sengaja TIDAK dibungkus DB::transaction() oleh method ini (dan harus
     * dipanggil di luar transaksi Service+Order Layanan oleh pemanggil) —
     * konsisten pola lama, walau sekarang tidak ada panggilan HTTP eksternal
     * di sini.
     *
     * $signedUrlExpiresAt dipakai modul Renewal (lihat RenewalService) untuk
     * memberi TTL signed URL yang lebih panjang dari invoice_ttl_days biasa
     * (link pembayaran perpanjangan harus tetap valid sampai service.expired_at,
     * bisa sampai 5 hari) — default null berarti perilaku registrasi biasa
     * (invoice_ttl_days) tidak berubah sama sekali.
     */
    public function createForServiceOrder(ServiceOrder $serviceOrder, ?CarbonInterface $signedUrlExpiresAt = null): ?Receipt
    {
        if ((float) $serviceOrder->grandtotal <= 0) {
            $serviceOrder->update(['settled_at' => now()]);

            return null;
        }

        $receipt = $serviceOrder->receipt ?? Receipt::create([
            'service_order_id' => $serviceOrder->id,
            'amount' => $serviceOrder->grandtotal,
            'status' => Receipt::STATUS_AWAITING_CHANNEL_SELECTION,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        if (! $receipt->code) {
            $receipt->update([
                'code' => 'REC'.str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT),
            ]);
        }

        if (! $receipt->checkout_url) {
            $receipt->update([
                'checkout_url' => URL::temporarySignedRoute(
                    'payment.show',
                    $signedUrlExpiresAt ?? now()->addDays((int) Setting::get('billing.invoice_ttl_days', config('billing.invoice_ttl_days'))),
                    ['receipt' => $receipt->id],
                ),
            ]);
        }

        if (! $serviceOrder->invoiced_at) {
            // Order Layanan renewal sengaja TIDAK diberi expired_at (tidak
            // ada konsep "jatuh tempo invoice 3 hari" untuknya — invoice
            // renewal harus tetap terbuka/bisa dibayar selama masa suspend,
            // cuma service.expired_at yang relevan). Ini yang membuat
            // CancelExpiredInvoices otomatis skip Order Layanan renewal,
            // lihat CLAUDE.md "Renewal".
            $serviceOrder->update([
                'invoiced_at' => now(),
                'expired_at' => $serviceOrder->is_renewal ? null : now()->addDays((int) Setting::get('billing.invoice_ttl_days', config('billing.invoice_ttl_days'))),
            ]);
        }

        $this->notificationService->send($serviceOrder->service->user, new InvoiceCreatedNotification($receipt));

        return $receipt;
    }

    /**
     * Pelanggan memilih channel pembayaran di halaman /pay/{receipt} —
     * baru di sini Payment Request Xendit sungguhan dibuat. Channel
     * cuma boleh diganti selama xendit_payment_request_id masih null
     * (percobaan sebelumnya belum pernah berhasil dapat id sungguhan dari
     * Xendit) — begitu sudah ada id sungguhan, ganti channel tidak
     * didukung di iterasi ini (staff/pelanggan tunggu channel itu
     * kadaluarsa lalu Order Layanan otomatis dibatalkan scheduler, konsisten
     * pola "retry setelah expired tidak didukung").
     */
    public function selectChannel(Receipt $receipt, string $channelCode): Receipt
    {
        if ($receipt->xendit_payment_request_id) {
            throw new RuntimeException('Channel pembayaran sudah dipilih dan tidak bisa diganti.');
        }

        $serviceOrder = $receipt->serviceOrder()->with('service.user')->first();

        $result = $this->gateway->createPaymentRequest(
            referenceId: $receipt->code,
            amount: (float) $receipt->amount,
            description: "Tagihan pendaftaran {$serviceOrder->code}",
            channelCode: $channelCode,
            channelProperties: $this->buildChannelProperties($channelCode, $serviceOrder, $receipt),
            type: $this->resolveRequestType($channelCode),
        );

        $receipt->update([
            'channel_code' => $channelCode,
            'xendit_payment_request_id' => $result['id'],
            'status' => $result['status'],
            'raw_response' => $result['raw'],
            'updated_by' => Auth::id(),
        ]);

        return $receipt->fresh();
    }

    /**
     * Virtual Account (channel_code berakhiran "_VIRTUAL_ACCOUNT") wajib
     * type=REUSABLE_PAYMENT_CODE — VA bersifat reusable/dipakai berulang
     * sampai kadaluarsa, beda semantik dari PAY yang sekali pakai. Dugaan
     * ini dari riset dokumentasi Xendit (bukan percobaan sandbox langsung
     * seperti channel lain) — lihat CLAUDE.md "Billing / Invoice (Xendit)".
     */
    private function resolveRequestType(string $channelCode): string
    {
        return str_ends_with($channelCode, '_VIRTUAL_ACCOUNT') ? 'REUSABLE_PAYMENT_CODE' : 'PAY';
    }

    /**
     * channel_properties per kategori best-effort — Xendit mengarahkan ke
     * "Channel Data Finder" widget interaktif untuk daftar field per
     * channel yang tidak terdokumentasi statis (lihat CLAUDE.md "Billing /
     * Invoice (Xendit)"). Revisit begitu ada channel yang gagal validasi
     * di sandbox.
     *
     * @return array<string, mixed>
     */
    private function buildChannelProperties(string $channelCode, ServiceOrder $serviceOrder, Receipt $receipt): array
    {
        $customerName = $serviceOrder->service->user->name;

        return match (true) {
            $channelCode === 'QRIS' => [],
            str_ends_with($channelCode, '_VIRTUAL_ACCOUNT') => [
                // display_name wajib (dikonfirmasi lewat percobaan
                // sungguhan ke sandbox - BCA_VIRTUAL_ACCOUNT ditolak tanpa
                // field ini) - nama yang tampil ke pelanggan saat transfer.
                'display_name' => $customerName,
            ],
            in_array($channelCode, ['ALFAMART', 'INDOMARET'], true) => [
                // payer_name wajib (dikonfirmasi lewat percobaan sungguhan
                // ke sandbox - INDOMARET ditolak dengan field customer_name,
                // beda dari kategori VA yang justru butuh customer_name).
                'payer_name' => $customerName,
            ],
            default => [],
        };
    }
}
