<?php

namespace Tests\Feature\Billing;

use App\Models\Coverage;
use App\Models\Package;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Subdistrict;
use App\Models\User;
use App\Models\UserDetail;
use App\Notifications\InvoiceCreatedNotification;
use App\Services\Billing\XenditGateway;
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

    public function test_creating_paid_service_creates_receipt_and_calls_xendit(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $customer = $this->customer();
        $package = Package::factory()->create(['is_starter' => true]);
        $product = Product::factory()->create(['price' => 150000]);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => 150000]);

        $this->registerService($customer, $package);

        $sale = Sale::firstOrFail();
        $receipt = Receipt::where('sale_id', $sale->id)->firstOrFail();

        $this->assertStringStartsWith('REC', $receipt->code);
        $this->assertSame("pr-{$receipt->code}", $receipt->xendit_payment_request_id);
        $this->assertSame('PENDING', $receipt->status);
        $this->assertNotNull($receipt->checkout_url);
        $this->assertEquals(150000, (float) $receipt->amount);

        $sale->refresh();
        $this->assertNotNull($sale->invoiced_at);
        $this->assertNotNull($sale->expired_at);
        $this->assertNull($sale->settled_at);

        $this->assertCount(1, $fake->calls);
        $this->assertSame($receipt->code, $fake->calls[0]['referenceId']);
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

        $sale = Sale::firstOrFail();
        $receipt = Receipt::where('sale_id', $sale->id)->firstOrFail();

        // Registrasi & tagihan sama-sama mengirim WhatsApp — pesan terakhir
        // yang tersimpan di gateway adalah InvoiceCreatedNotification
        // (dikirim setelah ServiceRegisteredNotification, lihat
        // ServiceService::create()).
        $this->assertSame((string) $customer->phone, $gateway->phone);
        $this->assertStringContainsString($sale->code, $gateway->message);
        $this->assertStringContainsString($receipt->checkout_url, $gateway->message);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'type' => InvoiceCreatedNotification::class,
        ]);
    }

    public function test_free_package_skips_xendit_and_settles_sale_immediately(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $customer = $this->customer();
        $package = Package::factory()->create(['is_starter' => true]);
        // Tidak ada produk sama sekali dibundel — grandtotal tetap 0.

        $this->registerService($customer, $package);

        $sale = Sale::firstOrFail();

        $this->assertEquals(0, (float) $sale->grandtotal);
        $this->assertNotNull($sale->settled_at);
        $this->assertNull($sale->invoiced_at);
        $this->assertDatabaseCount('receipts', 0);
        $this->assertCount(0, $fake->calls);
    }

    public function test_retry_creates_receipt_after_initial_xendit_call_failed(): void
    {
        $failingFake = new FakeXenditGateway;
        $failingFake->shouldFail = true;
        $this->app->instance(XenditGateway::class, $failingFake);

        $superadmin = $this->superadmin();
        $customer = $this->customer();
        $package = Package::factory()->create(['is_starter' => true]);
        $product = Product::factory()->create(['price' => 100000]);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => 100000]);

        $this->registerService($customer, $package);

        $sale = Sale::firstOrFail();
        $sale->refresh();
        $this->assertNull($sale->invoiced_at);

        $receipt = Receipt::where('sale_id', $sale->id)->first();
        $this->assertNotNull($receipt);
        $this->assertNull($receipt->xendit_payment_request_id);

        $workingFake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $workingFake);

        $response = $this->actingAs($superadmin)->post("/sales/{$sale->id}/receipt/retry");

        $response->assertRedirect(route('sales.show', $sale));

        $sale->refresh();
        $receipt->refresh();

        $this->assertNotNull($sale->invoiced_at);
        $this->assertNotNull($receipt->xendit_payment_request_id);
        $this->assertCount(1, $workingFake->calls);
    }
}
