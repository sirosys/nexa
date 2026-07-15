<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryUnit;
use App\Models\Package;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceActivation;
use App\Models\User;
use App\Notifications\ServiceActivatedNotification;
use App\Notifications\TechnicianAssignedNotification;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class InstallationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    /**
     * Service pending_installation + Sale registrasi yang sudah settled —
     * prasyarat yang dicari InstallationService::activationFor() untuk
     * menyambungkan service_activations.sale_id.
     */
    private function pendingInstallationService(?Package $package = null): Service
    {
        $package ??= Package::factory()->create(['is_starter' => true, 'plan_qty' => 1]);
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

    public function test_superadmin_can_assign_technician_and_service_becomes_installing(): void
    {
        $gateway = $this->fakeGateway();
        $service = $this->pendingInstallationService();
        $technician = $this->withRole('technician');

        $response = $this->actingAs($this->superadmin())->post("/installations/{$service->id}/assign", [
            'installer_id' => $technician->id,
        ]);

        $response->assertRedirect(route('installations.show', $service));

        $service->refresh();
        $this->assertSame(Service::STATUS_INSTALLING, $service->status);

        $activation = ServiceActivation::where('service_id', $service->id)->firstOrFail();
        $this->assertSame($technician->id, $activation->installer_id);
        $this->assertNotNull($activation->assigned_by);
        $this->assertNotNull($activation->claimed_at);

        $this->assertSame((string) $technician->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $technician->id,
            'type' => TechnicianAssignedNotification::class,
        ]);
    }

    public function test_assign_rejected_when_service_not_pending_installation(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_ACTIVE]);
        $technician = $this->withRole('technician');

        $response = $this->actingAs($this->superadmin())->post("/installations/{$service->id}/assign", [
            'installer_id' => $technician->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(Service::STATUS_ACTIVE, $service->fresh()->status);
    }

    public function test_assign_rejects_installer_not_technician_role(): void
    {
        $service = $this->pendingInstallationService();
        $notTechnician = $this->withRole('finance');

        $response = $this->actingAs($this->superadmin())->post("/installations/{$service->id}/assign", [
            'installer_id' => $notTechnician->id,
        ]);

        $response->assertSessionHasErrors('installer_id');
    }

    public function test_technician_can_claim_open_job(): void
    {
        $service = $this->pendingInstallationService();
        $technician = $this->withRole('technician');

        $response = $this->actingAs($technician)->post("/installations/{$service->id}/claim");

        $response->assertRedirect(route('installations.show', $service));

        $service->refresh();
        $this->assertSame(Service::STATUS_INSTALLING, $service->status);

        $activation = ServiceActivation::where('service_id', $service->id)->firstOrFail();
        $this->assertSame($technician->id, $activation->installer_id);
        $this->assertNull($activation->assigned_by);
    }

    public function test_claim_rejected_when_already_claimed_by_another_technician(): void
    {
        $service = $this->pendingInstallationService();
        $first = $this->withRole('technician');
        $second = $this->withRole('technician');

        $this->actingAs($first)->post("/installations/{$service->id}/claim");

        $response = $this->actingAs($second)->post("/installations/{$service->id}/claim");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $activation = ServiceActivation::where('service_id', $service->id)->firstOrFail();
        $this->assertSame($first->id, $activation->installer_id);
    }

    public function test_technician_can_complete_installation_and_service_becomes_active(): void
    {
        Storage::fake('local');
        $gateway = $this->fakeGateway();
        $package = Package::factory()->create(['is_starter' => true, 'plan_qty' => 3]);
        $service = $this->pendingInstallationService($package);
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/installations/{$service->id}/claim");

        $response = $this->actingAs($technician)->post("/installations/{$service->id}/complete", [
            'odp_port' => 'ODP-01-02',
            'cable_length' => '25.5',
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
            'notes' => 'Instalasi lancar.',
        ]);

        $response->assertRedirect(route('installations.show', $service));

        $service->refresh();
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
        $this->assertNotNull($service->activated_at);
        $this->assertEqualsWithDelta(
            $service->activated_at->copy()->addMonths(3)->timestamp,
            $service->expired_at->timestamp,
            5
        );

        $activation = ServiceActivation::where('service_id', $service->id)->firstOrFail();
        $this->assertSame('ODP-01-02', $activation->odp_port);
        $this->assertEquals(25.5, (float) $activation->cable_length);
        $this->assertSame('Instalasi lancar.', $activation->notes);
        $this->assertNotNull($activation->photo);
        $this->assertNotNull($activation->completed_at);
        Storage::disk('local')->assertExists($activation->photo);

        $this->assertSame((string) $service->user->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $service->user_id,
            'type' => ServiceActivatedNotification::class,
        ]);
    }

    public function test_complete_installation_consumes_equipment_from_inventory(): void
    {
        Storage::fake('local');
        $package = Package::factory()->create(['is_starter' => true, 'plan_qty' => 1]);
        $service = $this->pendingInstallationService($package);
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/installations/{$service->id}/claim");

        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => true, 'quantity' => 1]);
        InventoryUnit::create([
            'inventory_item_id' => $item->id,
            'serial_number' => 'SN-MODEM-01',
            'status' => InventoryUnit::STATUS_IN_STOCK,
        ]);

        $response = $this->actingAs($technician)->post("/installations/{$service->id}/complete", [
            'odp_port' => 'ODP-01-02',
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
            'equipment' => [
                ['inventory_item_id' => $item->id, 'serial_number' => 'SN-MODEM-01'],
            ],
        ]);

        $response->assertRedirect(route('installations.show', $service));
        $this->assertSame(Service::STATUS_ACTIVE, $service->fresh()->status);

        $item->refresh();
        $this->assertSame(0, $item->quantity);
        $unit = InventoryUnit::where('serial_number', 'SN-MODEM-01')->firstOrFail();
        $this->assertSame(InventoryUnit::STATUS_INSTALLED, $unit->status);
        $this->assertSame($service->id, $unit->service_id);
    }

    public function test_complete_installation_fails_when_equipment_stock_insufficient(): void
    {
        Storage::fake('local');
        $package = Package::factory()->create(['is_starter' => true, 'plan_qty' => 1]);
        $service = $this->pendingInstallationService($package);
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/installations/{$service->id}/claim");

        $product = Product::factory()->create(['type' => 'perangkat']);
        $item = InventoryItem::create(['product_id' => $product->id, 'is_serialized' => false, 'quantity' => 2]);

        $response = $this->actingAs($technician)->post("/installations/{$service->id}/complete", [
            'odp_port' => 'ODP-01-02',
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
            'equipment' => [
                ['inventory_item_id' => $item->id, 'quantity' => 5],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        // Rollback penuh — instalasi TIDAK ikut selesai kalau stok kurang.
        $this->assertSame(Service::STATUS_INSTALLING, $service->fresh()->status);
        $this->assertSame(2, $item->fresh()->quantity);
    }

    public function test_technician_who_is_not_assigned_installer_cannot_complete(): void
    {
        Storage::fake('local');
        $service = $this->pendingInstallationService();
        $assigned = $this->withRole('technician');
        $other = $this->withRole('technician');
        $this->actingAs($assigned)->post("/installations/{$service->id}/claim");

        $response = $this->actingAs($other)->post("/installations/{$service->id}/complete", [
            'odp_port' => 'ODP-01-02',
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertForbidden();
    }

    public function test_completion_requires_odp_port_and_photo(): void
    {
        $service = $this->pendingInstallationService();
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/installations/{$service->id}/claim");

        $response = $this->actingAs($technician)->post("/installations/{$service->id}/complete", []);

        $response->assertSessionHasErrors(['odp_port', 'photo']);
    }

    public function test_superadmin_cannot_claim(): void
    {
        $service = $this->pendingInstallationService();
        $superadmin = $this->superadmin();

        // Klaim tetap aksi fieldwork khusus technician — superadmin sengaja
        // tidak ikut kebagian walau punya seluruh permission lain (lihat
        // CLAUDE.md "Authorization / Role & Permission").
        $this->actingAs($superadmin)->post("/installations/{$service->id}/claim")->assertForbidden();
    }

    public function test_superadmin_can_complete_installation_via_override(): void
    {
        Storage::fake('local');
        $service = $this->pendingInstallationService();
        $superadmin = $this->superadmin();

        $service->update(['status' => Service::STATUS_INSTALLING]);
        ServiceActivation::create([
            'service_id' => $service->id,
            'sale_id' => Sale::where('service_id', $service->id)->firstOrFail()->id,
            'installer_id' => $this->withRole('technician')->id,
        ]);

        // installations.complete-any: jalur darurat kalau teknisi yang
        // di-assign resign/tidak aktif — menutup gap "job stuck permanen"
        // (lihat CLAUDE.md "Authorization / Role & Permission").
        $response = $this->actingAs($superadmin)->post("/installations/{$service->id}/complete", [
            'odp_port' => 'ODP-01-02',
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertRedirect(route('installations.show', $service));
        $this->assertSame(Service::STATUS_ACTIVE, $service->fresh()->status);
    }

    public function test_non_superadmin_non_technician_roles_forbidden_from_installation_routes(): void
    {
        $service = $this->pendingInstallationService();

        foreach (['finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/installations')->assertForbidden();
            $this->actingAs($staff)->get("/installations/{$service->id}")->assertForbidden();
        }
    }

    public function test_index_and_show_render_for_superadmin_and_technician(): void
    {
        $service = $this->pendingInstallationService();

        $this->actingAs($this->superadmin())->get('/installations')->assertOk();
        $this->actingAs($this->superadmin())->get("/installations/{$service->id}")->assertOk();

        $technician = $this->withRole('technician');
        $this->actingAs($technician)->get('/installations')->assertOk();
        $this->actingAs($technician)->get("/installations/{$service->id}")->assertOk();
    }

    public function test_installation_photo_accessible_by_assigned_installer_and_superadmin_only(): void
    {
        Storage::fake('local');
        $service = $this->pendingInstallationService();
        $technician = $this->withRole('technician');
        $other = $this->withRole('technician');
        $this->actingAs($technician)->post("/installations/{$service->id}/claim");
        $this->actingAs($technician)->post("/installations/{$service->id}/complete", [
            'odp_port' => 'ODP-01-02',
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $this->actingAs($technician)->get("/secure/installation-photo/{$service->id}")->assertOk();
        $this->actingAs($this->superadmin())->get("/secure/installation-photo/{$service->id}")->assertOk();
        $this->actingAs($other)->get("/secure/installation-photo/{$service->id}")->assertForbidden();
    }

    public function test_installation_photo_returns_404_when_not_yet_uploaded(): void
    {
        $service = $this->pendingInstallationService();

        $response = $this->actingAs($this->superadmin())->get("/secure/installation-photo/{$service->id}");

        $response->assertNotFound();
    }
}
