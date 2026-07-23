<?php

namespace Tests\Feature\Renewal;

use App\Models\Package;
use App\Models\Plan;
use App\Models\Receipt;
use App\Models\Service;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RenewalCreateInvoicesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * $packagePrice adalah packages.price (harga paket pendaftaran, SATU-
     * SATUNYA acuan harga paket sejak packages.plan_price dihapus) — beda
     * dari $catalogPrice (plans.price), yang seharusnya justru dipakai
     * RenewalService saat menagih perpanjangan, bukan harga paket ini
     * (lihat CLAUDE.md "Renewal"/"Product & Package"). Default keduanya
     * sama supaya test yang tidak spesifik menguji perbedaan ini tetap
     * sederhana.
     */
    private function packageWithPlan(float $catalogPrice = 200000, ?float $packagePrice = null): Package
    {
        $plan = Plan::factory()->create(['price' => $catalogPrice]);

        return Package::factory()->create([
            'is_starter' => false,
            'plan_id' => $plan->id,
            'price' => $packagePrice ?? $catalogPrice,
            'plan_qty' => 1,
        ]);
    }

    public function test_creates_renewal_invoice_when_service_within_h5_window(): void
    {
        $package = $this->packageWithPlan(200000);
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(3),
        ]);

        Artisan::call('renewal:create-invoices');

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->where('is_renewal', true)->first();

        $this->assertNotNull($serviceOrder);
        $this->assertEquals(200000, (float) $serviceOrder->grandtotal);
        $this->assertNotNull($serviceOrder->invoiced_at);
        $this->assertNull($serviceOrder->expired_at);

        $receipt = Receipt::where('service_order_id', $serviceOrder->id)->first();
        $this->assertNotNull($receipt);
        $this->assertSame(Receipt::STATUS_AWAITING_CHANNEL_SELECTION, $receipt->status);
    }

    public function test_does_not_duplicate_invoice_when_run_twice(): void
    {
        $package = $this->packageWithPlan();
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(2),
        ]);

        Artisan::call('renewal:create-invoices');
        Artisan::call('renewal:create-invoices');

        $this->assertSame(1, ServiceOrder::where('service_id', $service->id)->where('is_renewal', true)->count());
    }

    public function test_does_not_create_invoice_outside_window(): void
    {
        $package = $this->packageWithPlan();
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(10),
        ]);

        Artisan::call('renewal:create-invoices');

        $this->assertDatabaseMissing('service_orders', ['service_id' => $service->id, 'is_renewal' => true]);
    }

    public function test_does_not_touch_non_active_service(): void
    {
        $package = $this->packageWithPlan();
        $service = Service::factory()->create([
            'status' => Service::STATUS_PENDING_INSTALLATION,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(2),
        ]);

        Artisan::call('renewal:create-invoices');

        $this->assertDatabaseMissing('service_orders', ['service_id' => $service->id, 'is_renewal' => true]);
    }

    /**
     * Paket gratis/promo tidak lewat Xendit sama sekali (grandtotal 0) —
     * harus tetap memperpanjang expired_at (lewat reactivate() internal)
     * supaya command besok tidak membuat Order Layanan renewal gratis baru lagi.
     */
    public function test_free_package_renewal_auto_settles_and_extends_expiry_without_receipt(): void
    {
        $package = $this->packageWithPlan(0);
        $oldExpiredAt = now()->addDays(2);
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => $oldExpiredAt,
        ]);

        Artisan::call('renewal:create-invoices');

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->where('is_renewal', true)->first();
        $this->assertNotNull($serviceOrder);
        $this->assertEquals(0, (float) $serviceOrder->grandtotal);
        $this->assertNotNull($serviceOrder->settled_at);
        $this->assertDatabaseCount('receipts', 0);

        $service->refresh();
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
        $this->assertTrue($service->expired_at->greaterThan($oldExpiredAt));

        // Guard idempotency tetap efektif walau Order Layanan-nya settled: jalankan
        // command lagi tidak membuat Order Layanan renewal kedua.
        Artisan::call('renewal:create-invoices');
        $this->assertSame(1, ServiceOrder::where('service_id', $service->id)->where('is_renewal', true)->count());
    }

    /**
     * Bukti utama fix bug SAL000002: Order Layanan renewal cuma menagih Plan tier
     * paket ini (tidak ada baris service_order_products sama sekali — modem/instalasi
     * TIDAK ikut tertagih ulang), pada harga katalog Plan SAAT INI — bukan
     * packages.price milik paket registrasi ini (sengaja dibuat beda jauh
     * di bawah supaya assertion gagal tegas kalau RenewalService diam-diam
     * memakai packages.price, bukan plans.price).
     */
    public function test_renewal_invoice_only_bills_plan_at_current_catalog_price(): void
    {
        $package = $this->packageWithPlan(catalogPrice: 150000, packagePrice: 999999);

        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(3),
        ]);

        Artisan::call('renewal:create-invoices');

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->where('is_renewal', true)->firstOrFail();

        $this->assertCount(0, $serviceOrder->products);
        $this->assertSame($package->plan_id, $serviceOrder->plan_id);
        $this->assertEquals(150000, (float) $serviceOrder->plan_price);
        $this->assertSame(1, $serviceOrder->plan_qty);
        $this->assertEquals(150000, (float) $serviceOrder->grandtotal);
    }

    /**
     * service_orders.is_starter harus SELALU false untuk Order Layanan renewal, terlepas dari
     * is_starter paket registrasi yang masih terpasang di service_id-nya
     * (paket promo/starter) — regression untuk SAL000002.
     */
    public function test_renewal_invoice_is_never_marked_as_starter(): void
    {
        $package = $this->packageWithPlan(150000);
        $package->update(['is_starter' => true]);

        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(3),
        ]);

        Artisan::call('renewal:create-invoices');

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->where('is_renewal', true)->firstOrFail();

        $this->assertFalse($serviceOrder->is_starter);
    }

    /**
     * Kalau command ini telat jalan (scheduler down beberapa hari, dsb.)
     * dan expired_at Service SUDAH LEWAT saat invoice akhirnya dibuat, link
     * pembayaran tidak boleh lahir dalam keadaan sudah kadaluarsa (muncul
     * sebagai "Invalid signature" ke pelanggan begitu diklik) — harus di-floor
     * ke invoice_ttl_days dari SEKARANG. Reproduksi bug nyata yang ditemukan
     * user: expired_at 2026-07-13 21:00, invoice baru dibuat 2026-07-14 01:00.
     */
    public function test_checkout_url_is_never_generated_already_expired_when_service_is_already_overdue(): void
    {
        $package = $this->packageWithPlan(150000);
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->subHours(4),
        ]);

        Artisan::call('renewal:create-invoices');

        $serviceOrder = ServiceOrder::where('service_id', $service->id)->where('is_renewal', true)->firstOrFail();
        $receipt = Receipt::where('service_order_id', $serviceOrder->id)->firstOrFail();

        parse_str(parse_url($receipt->checkout_url, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('expires', $query);
        $this->assertGreaterThan(now()->timestamp, (int) $query['expires']);

        $request = Request::create($receipt->checkout_url, 'GET');
        $this->assertTrue($request->hasValidSignature());
    }

    /**
     * Service yang paketnya belum punya plan_id (data lama/belum lengkap)
     * dilewati dengan aman — tidak menghentikan batch renewal Service lain
     * yang sudah benar.
     */
    public function test_service_without_plan_is_skipped_without_crashing_batch(): void
    {
        $incompletePackage = Package::factory()->create(['is_starter' => false, 'plan_id' => null]);
        $incompleteService = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $incompletePackage->id,
            'expired_at' => now()->addDays(3),
        ]);

        $goodPackage = $this->packageWithPlan(150000);
        $goodService = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $goodPackage->id,
            'expired_at' => now()->addDays(3),
        ]);

        Artisan::call('renewal:create-invoices');

        $this->assertDatabaseMissing('service_orders', ['service_id' => $incompleteService->id, 'is_renewal' => true]);
        $this->assertDatabaseHas('service_orders', ['service_id' => $goodService->id, 'is_renewal' => true]);
    }
}
