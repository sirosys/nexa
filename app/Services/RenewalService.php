<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Notifications\RenewalReminderNotification;
use App\Notifications\ServiceReactivatedNotification;
use App\Notifications\ServiceSuspendedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Siklus billing perpanjangan Service: buat tagihan otomatis di H-5, kirim
 * reminder H-3/H-1 kalau masih belum dibayar, suspend Service yang telat
 * bayar, dan reaktivasi otomatis begitu pembayaran diterima. Lihat CLAUDE.md
 * "Renewal" untuk alur bisnis lengkap.
 */
class RenewalService
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly ReceiptService $receiptService,
        private readonly NotificationService $notificationService,
        private readonly MikrotikService $mikrotikService,
    ) {}

    /**
     * Buat Sale+Receipt perpanjangan untuk sebuah Service yang mendekati
     * expired_at, snapshot dari package_id service saat ini. Idempotent —
     * no-op kalau Service ini sudah punya Sale renewal terbuka (belum
     * settled/canceled), supaya command bisa jalan berkali-kali/telat
     * sehari tanpa menduplikasi tagihan.
     */
    public function createInvoiceForDueService(Service $service): ?Sale
    {
        $hasOpenRenewal = $service->sales()
            ->where('is_renewal', true)
            ->whereNull('settled_at')
            ->whereNull('canceled_at')
            ->exists();

        if ($hasOpenRenewal) {
            return null;
        }

        $package = $service->package;

        $sale = $this->saleService->create([
            'service_id' => $service->id,
            'package_id' => $service->package_id,
            'is_renewal' => true,
            'products' => $this->buildProductLines($package),
        ]);

        $this->receiptService->createForSale($sale, $service->expired_at);

        // Paket gratis/promo (grandtotal 0) di-auto-settled langsung oleh
        // createForSale() tanpa lewat webhook Xendit sama sekali — kalau
        // tidak direaktivasi di sini juga, expired_at Service tidak pernah
        // diperpanjang dan guard "sudah ada renewal terbuka" di atas tidak
        // lagi menghalangi (settled_at sudah terisi), jadi command besok
        // akan membuat Sale renewal gratis baru lagi tanpa henti.
        if ($sale->fresh()->settled_at !== null) {
            $this->reactivate($sale->fresh());
        }

        return $sale;
    }

    /**
     * Kirim reminder WhatsApp susulan (H-3 atau H-1) untuk Sale renewal yang
     * masih belum dibayar — merujuk checkout_url yang SAMA dari H-5, tidak
     * membuat Sale/Receipt baru. $daysRemaining menentukan kolom penanda
     * *_sent_at mana yang distempel (idempotency per hari reminder).
     */
    public function sendReminder(Sale $sale, int $daysRemaining): void
    {
        $column = $daysRemaining === 3 ? 'renewal_reminder_h3_sent_at' : 'renewal_reminder_h1_sent_at';

        $sale->update([$column => now()]);

        $this->notificationService->send(
            $sale->service->user,
            new RenewalReminderNotification($sale, $daysRemaining),
        );
    }

    /**
     * Suspend Service yang expired_at-nya sudah lewat dan belum dibayar.
     */
    public function suspend(Service $service): void
    {
        DB::transaction(function () use ($service) {
            $service->update([
                'status' => Service::STATUS_SUSPENDED,
                'suspended_at' => now(),
            ]);
        });

        $this->mikrotikService->disable($service);
        $this->notificationService->send($service->user, new ServiceSuspendedNotification($service));
    }

    /**
     * Dipanggil dari XenditWebhookController begitu Sale renewal SUCCEEDED.
     * Dipakai untuk DUA skenario sekaligus: pembayaran telat setelah
     * status=suspended (reaktivasi sungguhan) MAUPUN pembayaran tepat waktu
     * sebelum sempat suspend (status masih active) — logic-nya sama persis
     * di kedua kasus (extend expired_at dari nilai LAMA, paksa status
     * active, null-kan suspended_at), jadi aman dipanggil tanpa cek status
     * dulu; pada kasus kedua ini jadi no-op yang aman untuk status/suspended_at.
     */
    public function reactivate(Sale $sale): Service
    {
        $service = DB::transaction(function () use ($sale) {
            $service = $sale->service;
            $oldExpiredAt = $service->expired_at ?? now();
            $durationMonths = (int) ($sale->package->duration_months ?? $service->package->duration_months ?? 1);

            $service->update([
                'status' => Service::STATUS_ACTIVE,
                'expired_at' => $oldExpiredAt->copy()->addMonths($durationMonths),
                'suspended_at' => null,
            ]);

            return $service;
        });

        $this->mikrotikService->enable($service);
        $this->notificationService->send($service->user, new ServiceReactivatedNotification($service));

        return $service;
    }

    /**
     * Duplikasi sengaja dari ServiceService::buildProductLines() (private,
     * 6 baris) — bukan diekstrak ke trait/parent, konsisten gaya project
     * yang tidak bikin abstraksi untuk fungsi murni sekecil ini. Beda dari
     * versi ServiceService: di sini snapshot dari package Service SAAT INI
     * (langganan berjalan), bukan paket starter yang dipilih saat registrasi.
     *
     * @return array<int, array{product_id: int, price: float, quantity: int, unit: ?string}>
     */
    private function buildProductLines(Package $package): array
    {
        return $package->products->map(fn (Product $product) => [
            'product_id' => $product->id,
            'price' => (float) $product->pivot->price,
            'quantity' => (int) $product->pivot->quantity,
            'unit' => $product->unit,
        ])->values()->all();
    }
}
