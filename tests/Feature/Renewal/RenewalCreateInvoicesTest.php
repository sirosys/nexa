<?php

namespace Tests\Feature\Renewal;

use App\Models\Package;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RenewalCreateInvoicesTest extends TestCase
{
    use RefreshDatabase;

    private function packageWithProduct(float $price = 200000): Package
    {
        $package = Package::factory()->create(['is_starter' => false]);
        $product = Product::factory()->create(['price' => $price]);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => $price]);

        return $package;
    }

    public function test_creates_renewal_invoice_when_service_within_h5_window(): void
    {
        $package = $this->packageWithProduct(200000);
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(3),
        ]);

        Artisan::call('renewal:create-invoices');

        $sale = Sale::where('service_id', $service->id)->where('is_renewal', true)->first();

        $this->assertNotNull($sale);
        $this->assertEquals(200000, (float) $sale->grandtotal);
        $this->assertNotNull($sale->invoiced_at);
        $this->assertNull($sale->expired_at);

        $receipt = Receipt::where('sale_id', $sale->id)->first();
        $this->assertNotNull($receipt);
        $this->assertSame(Receipt::STATUS_AWAITING_CHANNEL_SELECTION, $receipt->status);
    }

    public function test_does_not_duplicate_invoice_when_run_twice(): void
    {
        $package = $this->packageWithProduct();
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(2),
        ]);

        Artisan::call('renewal:create-invoices');
        Artisan::call('renewal:create-invoices');

        $this->assertSame(1, Sale::where('service_id', $service->id)->where('is_renewal', true)->count());
    }

    public function test_does_not_create_invoice_outside_window(): void
    {
        $package = $this->packageWithProduct();
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(10),
        ]);

        Artisan::call('renewal:create-invoices');

        $this->assertDatabaseMissing('sales', ['service_id' => $service->id, 'is_renewal' => true]);
    }

    public function test_does_not_touch_non_active_service(): void
    {
        $package = $this->packageWithProduct();
        $service = Service::factory()->create([
            'status' => Service::STATUS_PENDING_INSTALLATION,
            'package_id' => $package->id,
            'expired_at' => now()->addDays(2),
        ]);

        Artisan::call('renewal:create-invoices');

        $this->assertDatabaseMissing('sales', ['service_id' => $service->id, 'is_renewal' => true]);
    }

    /**
     * Paket gratis/promo tidak lewat Xendit sama sekali (grandtotal 0) —
     * harus tetap memperpanjang expired_at (lewat reactivate() internal)
     * supaya command besok tidak membuat Sale renewal gratis baru lagi.
     */
    public function test_free_package_renewal_auto_settles_and_extends_expiry_without_receipt(): void
    {
        $package = Package::factory()->create(['is_starter' => false, 'duration_months' => 1]);
        $oldExpiredAt = now()->addDays(2);
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => $oldExpiredAt,
        ]);

        Artisan::call('renewal:create-invoices');

        $sale = Sale::where('service_id', $service->id)->where('is_renewal', true)->first();
        $this->assertNotNull($sale);
        $this->assertEquals(0, (float) $sale->grandtotal);
        $this->assertNotNull($sale->settled_at);
        $this->assertDatabaseCount('receipts', 0);

        $service->refresh();
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
        $this->assertTrue($service->expired_at->greaterThan($oldExpiredAt));

        // Guard idempotency tetap efektif walau Sale-nya settled: jalankan
        // command lagi tidak membuat Sale renewal kedua.
        Artisan::call('renewal:create-invoices');
        $this->assertSame(1, Sale::where('service_id', $service->id)->where('is_renewal', true)->count());
    }
}
