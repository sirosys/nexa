<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Setting;
use App\Notifications\RenewalReminderNotification;
use App\Notifications\ServiceReactivatedNotification;
use App\Notifications\ServiceSuspendedNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Siklus billing perpanjangan Service: buat tagihan otomatis di H-5, kirim
 * reminder H-3/H-1 kalau masih belum dibayar, suspend Service yang telat
 * bayar, dan reaktivasi otomatis begitu pembayaran diterima. Lihat CLAUDE.md
 * "Renewal" untuk alur bisnis lengkap.
 */
class RenewalService
{
    public function __construct(
        private readonly ServiceOrderService $serviceOrderService,
        private readonly ReceiptService $receiptService,
        private readonly NotificationService $notificationService,
        private readonly MikrotikService $mikrotikService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * Buat Order Layanan+Receipt perpanjangan untuk sebuah Service yang
     * mendekati expired_at — SELALU 1 bulan, menagih HANYA Plan tier paket
     * Service ini (bukan seluruh bundel registrasi), pada harga katalog Plan
     * SAAT INI (bukan harga snapshot promo registrasi). Lihat CLAUDE.md
     * "Renewal" dan "Plan" untuk alasan lengkap desain ini.
     *
     * Idempotent — no-op kalau Service ini sudah punya Order Layanan
     * renewal terbuka (belum settled/canceled), supaya command bisa jalan
     * berkali-kali/telat sehari tanpa menduplikasi tagihan. Guard ini juga
     * yang menegakkan aturan "perpanjangan non-default (masa depan, lewat
     * customer app/API) ditolak selama tagihan default H-5 masih terbuka".
     */
    public function createInvoiceForDueService(Service $service): ?ServiceOrder
    {
        if ($this->hasOpenRenewalInvoice($service)) {
            return null;
        }

        $plan = $service->package->plan;

        if (! $plan) {
            throw new RuntimeException("Paket layanan {$service->code} belum punya plan — lengkapi dulu lewat halaman Paket.");
        }

        $serviceOrder = $this->serviceOrderService->create([
            'service_id' => $service->id,
            'package_id' => $service->package_id,
            'is_renewal' => true,
            'plan_id' => $plan->id,
            'plan_price' => (float) $plan->price,
            'plan_qty' => 1,
            'products' => [],
        ]);

        // $service->expired_at seharusnya masih di masa depan (command ini
        // baru memprosesnya begitu masuk window H-5), tapi kalau command
        // sempat telat jalan (scheduler down beberapa hari, atau Service ini
        // sempat gagal diproses siklus sebelumnya) expired_at bisa sudah
        // LEWAT saat invoice akhirnya dibuat — signed URL dengan expiry di
        // masa lalu akan lahir dalam keadaan sudah kadaluarsa (link mati
        // begitu dibuat, muncul sebagai "Invalid signature" ke pelanggan).
        // Floor ke invoice_ttl_days dari SEKARANG supaya pelanggan tetap
        // dapat window wajar untuk bayar, bukan link yang sudah mati.
        $signedUrlExpiresAt = $service->expired_at->isFuture()
            ? $service->expired_at
            : now()->addDays((int) Setting::get('billing.invoice_ttl_days', config('billing.invoice_ttl_days')));

        $this->receiptService->createForServiceOrder($serviceOrder, $signedUrlExpiresAt);

        // Paket gratis/promo (grandtotal 0) di-auto-settled langsung oleh
        // createForServiceOrder() tanpa lewat webhook Xendit sama sekali —
        // kalau tidak direaktivasi di sini juga, expired_at Service tidak
        // pernah diperpanjang dan guard "sudah ada renewal terbuka" di atas
        // tidak lagi menghalangi (settled_at sudah terisi), jadi command
        // besok akan membuat Order Layanan renewal gratis baru lagi tanpa
        // henti.
        if ($serviceOrder->fresh()->settled_at !== null) {
            $this->reactivate($serviceOrder->fresh());
        }

        return $serviceOrder;
    }

    private function hasOpenRenewalInvoice(Service $service): bool
    {
        return $service->serviceOrders()
            ->where('is_renewal', true)
            ->whereNull('settled_at')
            ->whereNull('canceled_at')
            ->exists();
    }

    /**
     * Kirim reminder WhatsApp susulan (H-3 atau H-1) untuk Order Layanan
     * renewal yang masih belum dibayar — merujuk checkout_url yang SAMA
     * dari H-5, tidak membuat Order Layanan/Receipt baru. $daysRemaining
     * menentukan kolom penanda *_sent_at mana yang distempel (idempotency
     * per hari reminder).
     */
    public function sendReminder(ServiceOrder $serviceOrder, int $daysRemaining): void
    {
        $column = $daysRemaining === 3 ? 'renewal_reminder_h3_sent_at' : 'renewal_reminder_h1_sent_at';

        $serviceOrder->update([$column => now()]);

        $this->notificationService->send(
            $serviceOrder->service->user,
            new RenewalReminderNotification($serviceOrder, $daysRemaining),
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
        $this->auditLogService->record('service.suspended', $service, "Layanan {$service->code} disuspend otomatis karena telat bayar.");
    }

    /**
     * Dipanggil dari XenditWebhookController begitu Order Layanan renewal
     * SUCCEEDED. Dipakai untuk DUA skenario sekaligus: pembayaran telat
     * setelah status=suspended (reaktivasi sungguhan) MAUPUN pembayaran
     * tepat waktu sebelum sempat suspend (status masih active) — logic-nya
     * sama persis di kedua kasus (extend expired_at dari nilai LAMA, paksa
     * status active, null-kan suspended_at), jadi aman dipanggil tanpa cek
     * status dulu; pada kasus kedua ini jadi no-op yang aman untuk
     * status/suspended_at.
     */
    public function reactivate(ServiceOrder $serviceOrder): Service
    {
        $service = DB::transaction(function () use ($serviceOrder) {
            $service = $serviceOrder->service;
            $oldExpiredAt = $service->expired_at ?? now();

            // Durasi perpanjangan = plan_qty Order Layanan renewal ITU
            // SENDIRI (bukan lagi package->duration_months/base product) —
            // service_orders.package_id tetap paket registrasi asli (mis.
            // promo 3 bulan), yang durasinya tidak relevan lagi untuk siklus
            // renewal. Nilainya SELALU 1 untuk renewal otomatis saat ini,
            // tapi ditulis generik lewat plan_qty Order Layanan supaya siap
            // dipakai juga oleh perpanjangan non-default (quantity > 1)
            // begitu customer app/API dibangun — lihat CLAUDE.md "Renewal".
            $monthsToExtend = (int) ($serviceOrder->plan_qty ?? 1);

            $service->update([
                'status' => Service::STATUS_ACTIVE,
                'expired_at' => $oldExpiredAt->copy()->addMonths($monthsToExtend),
                'suspended_at' => null,
            ]);

            return $service;
        });

        $this->mikrotikService->enable($service);
        $this->notificationService->send($service->user, new ServiceReactivatedNotification($service));
        $this->auditLogService->record('service.reactivated', $service, "Layanan {$service->code} diaktifkan kembali setelah pembayaran perpanjangan diterima.");

        return $service;
    }
}
