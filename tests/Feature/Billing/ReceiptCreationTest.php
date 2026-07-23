<?php

namespace Tests\Feature\Billing;

use App\Models\Coverage;
use App\Models\Package;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\Subdistrict;
use App\Models\User;
use App\Models\UserDetail;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\Billing\XenditGateway;
use App\Services\ReceiptService;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CapturingWhatsappGateway;
use Tests\Support\FakeXenditGateway;
use Tests\Support\GeneratesValidNik;
use Tests\TestCase;

class ReceiptCreationTest extends TestCase
{
    use GeneratesValidNik, RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function fakeWhatsappGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    /**
     * Customer "lengkap" (NIK & foto KTP terisi) — wajib sejak gate
     * pendaftaran Service baru (lihat CLAUDE.md "Service"), kalau tidak
     * POST /services di registerService() akan ditolak validasi.
     */
    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $nik = $this->validNik();
        $user->userDetails()->create(array_merge(
            ['nik' => $nik, 'ktp_photo' => 'ktp/fake-test-photo.jpg'],
            UserDetail::parseNik($nik)
        ));

        return $user;
    }

    private function registerService(User $customer, Package $package): void
    {
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();

        $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'Jl. Billing No. 1',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);
    }

    /**
     * Sejak halaman pilih channel (/pay/{receipt}) dibangun,
     * createForServiceOrder() TIDAK lagi memanggil Xendit sama sekali —
     * Payment Requests API v3 tidak punya halaman checkout hosted
     * multi-channel, jadi panggilan Xendit sungguhan baru terjadi di
     * ReceiptService::selectChannel() begitu pelanggan memilih channel di
     * halaman itu (lihat PaymentChannelSelectionTest).
     */
    public function test_creating_paid_service_creates_receipt_awaiting_channel_selection(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $customer = $this->customer();
        // price=0 — isolasi test ini ke perhitungan service_order_products
        // saja, tanpa kontribusi packages.price (lihat CLAUDE.md "Product &
        // Package"/"Service Order").
        $package = Package::factory()->create(['is_starter' => true, 'price' => 0]);
        $product = Product::factory()->create(['price' => 150000]);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => 150000]);

        $this->registerService($customer, $package);

        $serviceOrder = ServiceOrder::firstOrFail();
        $receipt = Receipt::where('service_order_id', $serviceOrder->id)->firstOrFail();

        $this->assertStringStartsWith('REC', $receipt->code);
        $this->assertNull($receipt->xendit_payment_request_id);
        $this->assertNull($receipt->channel_code);
        $this->assertSame(Receipt::STATUS_AWAITING_CHANNEL_SELECTION, $receipt->status);
        $this->assertNotNull($receipt->checkout_url);
        $this->assertStringContainsString('/pay/'.$receipt->id, $receipt->checkout_url);
        $this->assertEquals(150000, (float) $receipt->amount);

        $serviceOrder->refresh();
        $this->assertNotNull($serviceOrder->invoiced_at);
        $this->assertNotNull($serviceOrder->expired_at);
        $this->assertNull($serviceOrder->settled_at);

        // Xendit belum dipanggil sama sekali sampai pelanggan memilih channel.
        $this->assertCount(0, $fake->calls);
    }

    public function test_creating_receipt_sends_whatsapp_invoice_notification(): void
    {
        $this->app->instance(XenditGateway::class, new FakeXenditGateway);
        $gateway = $this->fakeWhatsappGateway();

        $customer = $this->customer();
        $package = Package::factory()->create(['is_starter' => true]);
        $product = Product::factory()->create(['price' => 150000]);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => 150000]);

        $this->registerService($customer, $package);

        $serviceOrder = ServiceOrder::firstOrFail();
        $receipt = Receipt::where('service_order_id', $serviceOrder->id)->firstOrFail();

        // Registrasi & tagihan sama-sama mengirim WhatsApp — pesan terakhir
        // yang tersimpan di gateway adalah InvoiceCreatedNotification
        // (dikirim setelah ServiceRegisteredNotification, lihat
        // ServiceService::create()).
        $this->assertSame((string) $customer->phone, $gateway->phone);
        $this->assertStringContainsString($serviceOrder->code, $gateway->message);
        $this->assertStringContainsString($receipt->checkout_url, $gateway->message);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'type' => InvoiceCreatedNotification::class,
        ]);
    }

    public function test_free_package_skips_xendit_and_settles_service_order_immediately(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $customer = $this->customer();
        // price=0 (bypass validasi PackageRequest lewat factory — sejak
        // 2026-07-16 harga paket pendaftaran tidak boleh 0 lewat form,
        // lihat CLAUDE.md "Product & Package") — dipaksa di sini murni
        // untuk menjaga jalur defensif "Order Layanan gratis" tetap teruji,
        // bukan skenario yang bisa dicapai lewat UI staff lagi.
        $package = Package::factory()->create(['is_starter' => true, 'price' => 0]);
        // Tidak ada produk sama sekali dibundel — grandtotal tetap 0.

        $this->registerService($customer, $package);

        $serviceOrder = ServiceOrder::firstOrFail();

        $this->assertEquals(0, (float) $serviceOrder->grandtotal);
        $this->assertNotNull($serviceOrder->settled_at);
        $this->assertNull($serviceOrder->invoiced_at);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertCount(0, $fake->calls);
    }

    /**
     * createForServiceOrder() sekarang murni operasi database (tidak ada
     * panggilan HTTP), jadi tidak bisa gagal seperti pola lama — tombol
     * "Buat Tagihan" (retryReceipt) sekarang murni idempotent: dipanggil
     * lagi pada Order Layanan yang sudah py invoiced_at tidak melakukan
     * apa-apa, bukan "retry setelah gagal" seperti sebelumnya.
     */
    /**
     * Modul Renewal (lihat CLAUDE.md "Renewal") memanggil
     * createForServiceOrder() dengan parameter kedua eksplisit — path
     * registrasi (tanpa parameter) harus byte-identik dengan perilaku
     * sebelumnya, path renewal harus meninggalkan service_order.expired_at
     * null dan pakai TTL custom untuk signed URL.
     */
    public function test_create_for_service_order_leaves_expired_at_null_for_renewal_with_custom_signed_url_ttl(): void
    {
        $this->app->instance(XenditGateway::class, new FakeXenditGateway);

        $service = Service::factory()->create(['expired_at' => now()->addDays(5)]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'is_renewal' => true,
            'grandtotal' => 100000,
        ]);

        $receiptService = app(ReceiptService::class);
        $receiptService->createForServiceOrder($serviceOrder, $service->expired_at);

        $serviceOrder->refresh();
        $this->assertNotNull($serviceOrder->invoiced_at);
        $this->assertNull($serviceOrder->expired_at);
    }

    public function test_retry_is_idempotent_and_does_not_duplicate_receipt(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $superadmin = $this->superadmin();
        $customer = $this->customer();
        $package = Package::factory()->create(['is_starter' => true]);
        $product = Product::factory()->create(['price' => 100000]);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => 100000]);

        $this->registerService($customer, $package);

        $serviceOrder = ServiceOrder::firstOrFail();
        $receipt = Receipt::where('service_order_id', $serviceOrder->id)->firstOrFail();

        $response = $this->actingAs($superadmin)->post("/service-orders/{$serviceOrder->code}/receipt/retry");

        $response->assertRedirect(route('service-orders.show', $serviceOrder));
        $this->assertDatabaseCount('receipts', 1);

        $receipt->refresh();
        $this->assertNull($receipt->xendit_payment_request_id);
        $this->assertCount(0, $fake->calls);
    }

    /**
     * service_orders.retry-receipt dibuka untuk role finance (bukan
     * service_orders.update penuh) — lihat CLAUDE.md "Authorization / Role
     * & Permission".
     */
    public function test_finance_role_can_retry_receipt(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');

        $customer = $this->customer();
        $package = Package::factory()->create(['is_starter' => true]);
        $product = Product::factory()->create(['price' => 100000]);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => 100000]);

        $this->registerService($customer, $package);

        $serviceOrder = ServiceOrder::firstOrFail();

        $response = $this->actingAs($finance)->post("/service-orders/{$serviceOrder->code}/receipt/retry");

        $response->assertRedirect(route('service-orders.show', $serviceOrder));
    }
}
