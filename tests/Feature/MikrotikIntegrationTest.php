<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceActivation;
use App\Models\Site;
use App\Models\User;
use App\Services\DismantleService;
use App\Services\InstallationService;
use App\Services\Mikrotik\MikrotikGateway;
use App\Services\MikrotikService;
use App\Services\RenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CapturingMikrotikGateway;
use Tests\TestCase;

/**
 * Verifikasi wiring MikrotikService ke 4 titik trigger (Installation
 * complete, Renewal suspend/reactivate, Dismantle complete) — lihat
 * CLAUDE.md "Integrasi MikroTik". Tidak menguji driver 'log' itu sendiri
 * (LogMikrotikGateway cuma menulis ke storage/logs, tidak ada assertable
 * side effect selain itu) — fokus ke MikrotikGateway::* terpanggil dengan
 * argumen yang benar di titik yang benar, dan kegagalan gateway tidak
 * pernah menggagalkan alur bisnis.
 */
class MikrotikIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(): CapturingMikrotikGateway
    {
        $gateway = new CapturingMikrotikGateway;
        $this->app->instance(MikrotikGateway::class, $gateway);

        return $gateway;
    }

    private function pendingInstallationService(): Service
    {
        $package = Package::factory()->create(['is_starter' => true, 'plan_qty' => 1]);
        $service = Service::factory()->create([
            'package_id' => $package->id,
            'status' => Service::STATUS_PENDING_INSTALLATION,
        ]);
        Sale::factory()->create([
            'service_id' => $service->id,
            'package_id' => $package->id,
            'settled_at' => now(),
        ]);

        return $service;
    }

    public function test_installation_complete_provisions_pppoe_secret(): void
    {
        Storage::fake('local');
        $gateway = $this->fakeGateway();
        $service = $this->pendingInstallationService();
        $technician = User::factory()->create();
        $technician->assignRole('technician');
        app(InstallationService::class)->claim($service, $technician);
        $service = $service->fresh();

        app(InstallationService::class)->complete($service, [
            'odp_port' => 'ODP-01-02',
            'cable_length' => null,
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
            'notes' => null,
        ]);

        $service->refresh()->loadMissing('coverage.site');
        $this->assertCount(1, $gateway->calls);
        $this->assertSame('createPppoeSecret', $gateway->calls[0]['action']);
        $this->assertSame($service->coverage->site_id, $gateway->calls[0]['site_id']);
        $this->assertSame($service->code, $gateway->calls[0]['username']);
        $this->assertSame($service->pin, $gateway->calls[0]['password']);
    }

    public function test_installation_complete_succeeds_even_when_mikrotik_gateway_fails(): void
    {
        Storage::fake('local');
        $gateway = $this->fakeGateway();
        $gateway->shouldFail = true;
        $service = $this->pendingInstallationService();
        $technician = User::factory()->create();
        $technician->assignRole('technician');
        app(InstallationService::class)->claim($service, $technician);
        $service = $service->fresh();

        $result = app(InstallationService::class)->complete($service, [
            'odp_port' => 'ODP-01-02',
            'cable_length' => null,
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
            'notes' => null,
        ]);

        $this->assertSame(Service::STATUS_ACTIVE, $result->status);
        $this->assertNotCount(0, $gateway->calls);
    }

    public function test_renewal_suspend_disables_pppoe_secret(): void
    {
        $gateway = $this->fakeGateway();
        $service = Service::factory()->create(['status' => Service::STATUS_ACTIVE]);

        app(RenewalService::class)->suspend($service);

        $this->assertCount(1, $gateway->calls);
        $this->assertSame('disablePppoeSecret', $gateway->calls[0]['action']);
        $this->assertSame($service->code, $gateway->calls[0]['username']);
    }

    public function test_renewal_reactivate_enables_pppoe_secret(): void
    {
        $gateway = $this->fakeGateway();
        $service = Service::factory()->create([
            'status' => Service::STATUS_SUSPENDED,
            'expired_at' => now()->subDay(),
        ]);
        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'package_id' => $service->package_id,
            'is_renewal' => true,
        ]);

        app(RenewalService::class)->reactivate($sale);

        $this->assertCount(1, $gateway->calls);
        $this->assertSame('enablePppoeSecret', $gateway->calls[0]['action']);
        $this->assertSame($service->code, $gateway->calls[0]['username']);
    }

    public function test_dismantle_complete_removes_pppoe_secret(): void
    {
        Storage::fake('local');
        $gateway = $this->fakeGateway();
        $package = Package::factory()->create(['is_starter' => true, 'plan_qty' => 1]);
        $service = Service::factory()->create([
            'package_id' => $package->id,
            'status' => Service::STATUS_SUSPENDED,
        ]);
        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'package_id' => $package->id,
            'settled_at' => now(),
        ]);
        ServiceActivation::create([
            'service_id' => $service->id,
            'sale_id' => $sale->id,
        ]);
        $dismantleService = app(DismantleService::class);
        $dismantleService->queue($service);
        $technician = User::factory()->create();
        $technician->assignRole('technician');
        $dismantleService->claim($service->fresh(), $technician);

        $dismantleService->complete($service->fresh(), [
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
            'notes' => null,
        ]);

        $this->assertCount(1, $gateway->calls);
        $this->assertSame('deletePppoeSecret', $gateway->calls[0]['action']);
        $this->assertSame($service->code, $gateway->calls[0]['username']);
    }

    public function test_check_status_returns_gateway_result(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->reachable = true;
        $site = Site::factory()->create();

        $this->assertTrue(app(MikrotikService::class)->checkStatus($site));
        $this->assertSame('isReachable', $gateway->calls[0]['action']);
    }

    public function test_check_status_returns_false_and_does_not_throw_when_gateway_fails(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->shouldFail = true;
        $site = Site::factory()->create();

        $this->assertFalse(app(MikrotikService::class)->checkStatus($site));
    }
}
