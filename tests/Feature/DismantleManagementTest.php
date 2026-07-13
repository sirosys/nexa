<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceActivation;
use App\Models\ServiceDismantle;
use App\Models\User;
use App\Notifications\ServiceDismantledNotification;
use App\Notifications\TechnicianAssignedForDismantleNotification;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class DismantleManagementTest extends TestCase
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
     * Service dengan status tertentu + ServiceActivation settled — prasyarat
     * yang dicari DismantleService::queue() untuk mengisi
     * service_dismantles.activation_id. Mirror
     * InstallationManagementTest::pendingInstallationService().
     */
    private function serviceWithActivation(string $status, array $overrides = []): Service
    {
        $package = Package::factory()->create(['is_starter' => true, 'duration_months' => 1]);
        $service = Service::factory()->create(array_merge([
            'package_id' => $package->id,
            'status' => $status,
        ], $overrides));

        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'package_id' => $package->id,
            'settled_at' => now(),
        ]);

        ServiceActivation::create([
            'service_id' => $service->id,
            'sale_id' => $sale->id,
        ]);

        return $service;
    }

    /**
     * Service pending_dismantle + ServiceDismantle row — prasyarat untuk
     * test assign/claim/complete (mirip pendingInstallationService, tapi
     * satu langkah lebih lanjut karena Dismantle butuh langkah queue()
     * eksplisit sebelum assign/claim tersedia).
     */
    private function queuedForDismantleService(?string $fromStatus = Service::STATUS_SUSPENDED): Service
    {
        $service = $this->serviceWithActivation($fromStatus);

        ServiceDismantle::create([
            'service_id' => $service->id,
            'activation_id' => $service->activation->id,
        ]);

        $service->update(['status' => Service::STATUS_PENDING_DISMANTLE]);

        return $service->fresh();
    }

    public function test_superadmin_can_queue_dismantle_from_active_status(): void
    {
        $service = $this->serviceWithActivation(Service::STATUS_ACTIVE);
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->post("/dismantles/{$service->id}/queue");

        $response->assertRedirect(route('dismantles.show', $service));

        $service->refresh();
        $this->assertSame(Service::STATUS_PENDING_DISMANTLE, $service->status);

        $dismantle = ServiceDismantle::where('service_id', $service->id)->firstOrFail();
        $this->assertSame($service->activation->id, $dismantle->activation_id);
        $this->assertSame($superadmin->id, $dismantle->queued_by);
    }

    public function test_superadmin_can_queue_dismantle_from_suspended_status(): void
    {
        $service = $this->serviceWithActivation(Service::STATUS_SUSPENDED, ['suspended_at' => now()->subMonths(3)]);

        $response = $this->actingAs($this->superadmin())->post("/dismantles/{$service->id}/queue");

        $response->assertRedirect(route('dismantles.show', $service));
        $this->assertSame(Service::STATUS_PENDING_DISMANTLE, $service->fresh()->status);
    }

    public function test_queue_rejected_for_invalid_status(): void
    {
        $service = $this->serviceWithActivation(Service::STATUS_INSTALLING);

        $response = $this->actingAs($this->superadmin())->post("/dismantles/{$service->id}/queue");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(Service::STATUS_INSTALLING, $service->fresh()->status);
    }

    public function test_queue_rejected_when_already_queued(): void
    {
        $service = $this->queuedForDismantleService();

        $response = $this->actingAs($this->superadmin())->post("/dismantles/{$service->id}/queue");

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(1, ServiceDismantle::where('service_id', $service->id)->count());
    }

    public function test_queue_forbidden_for_non_superadmin(): void
    {
        $service = $this->serviceWithActivation(Service::STATUS_ACTIVE);

        foreach (['finance', 'sales', 'customer', 'technician'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->post("/dismantles/{$service->id}/queue")->assertForbidden();
        }
    }

    public function test_superadmin_can_assign_technician_and_service_becomes_dismantling(): void
    {
        $gateway = $this->fakeGateway();
        $service = $this->queuedForDismantleService();
        $technician = $this->withRole('technician');

        $response = $this->actingAs($this->superadmin())->post("/dismantles/{$service->id}/assign", [
            'technician_id' => $technician->id,
        ]);

        $response->assertRedirect(route('dismantles.show', $service));

        $service->refresh();
        $this->assertSame(Service::STATUS_DISMANTLING, $service->status);

        $dismantle = ServiceDismantle::where('service_id', $service->id)->firstOrFail();
        $this->assertSame($technician->id, $dismantle->technician_id);
        $this->assertNotNull($dismantle->assigned_by);
        $this->assertNotNull($dismantle->claimed_at);

        $this->assertSame((string) $technician->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $technician->id,
            'type' => TechnicianAssignedForDismantleNotification::class,
        ]);
    }

    public function test_assign_rejected_when_service_not_pending_dismantle(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_ACTIVE]);
        $technician = $this->withRole('technician');

        $response = $this->actingAs($this->superadmin())->post("/dismantles/{$service->id}/assign", [
            'technician_id' => $technician->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertSame(Service::STATUS_ACTIVE, $service->fresh()->status);
    }

    public function test_assign_rejects_technician_not_technician_role(): void
    {
        $service = $this->queuedForDismantleService();
        $notTechnician = $this->withRole('finance');

        $response = $this->actingAs($this->superadmin())->post("/dismantles/{$service->id}/assign", [
            'technician_id' => $notTechnician->id,
        ]);

        $response->assertSessionHasErrors('technician_id');
    }

    public function test_technician_can_claim_open_dismantle_job(): void
    {
        $service = $this->queuedForDismantleService();
        $technician = $this->withRole('technician');

        $response = $this->actingAs($technician)->post("/dismantles/{$service->id}/claim");

        $response->assertRedirect(route('dismantles.show', $service));

        $service->refresh();
        $this->assertSame(Service::STATUS_DISMANTLING, $service->status);

        $dismantle = ServiceDismantle::where('service_id', $service->id)->firstOrFail();
        $this->assertSame($technician->id, $dismantle->technician_id);
        $this->assertNull($dismantle->assigned_by);
    }

    public function test_claim_rejected_when_already_claimed_by_another_technician(): void
    {
        $service = $this->queuedForDismantleService();
        $first = $this->withRole('technician');
        $second = $this->withRole('technician');

        $this->actingAs($first)->post("/dismantles/{$service->id}/claim");

        $response = $this->actingAs($second)->post("/dismantles/{$service->id}/claim");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $dismantle = ServiceDismantle::where('service_id', $service->id)->firstOrFail();
        $this->assertSame($first->id, $dismantle->technician_id);
    }

    public function test_technician_can_complete_dismantle_and_service_becomes_dismantled(): void
    {
        Storage::fake('local');
        $gateway = $this->fakeGateway();
        $service = $this->queuedForDismantleService();
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/dismantles/{$service->id}/claim");

        $response = $this->actingAs($technician)->post("/dismantles/{$service->id}/complete", [
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
            'notes' => 'Perangkat berhasil diambil kembali.',
        ]);

        $response->assertRedirect(route('dismantles.show', $service));

        $service->refresh();
        $this->assertSame(Service::STATUS_DISMANTLED, $service->status);
        $this->assertNotNull($service->dismantled_at);

        $dismantle = ServiceDismantle::where('service_id', $service->id)->firstOrFail();
        $this->assertSame('Perangkat berhasil diambil kembali.', $dismantle->notes);
        $this->assertNotNull($dismantle->photo);
        $this->assertNotNull($dismantle->completed_at);
        Storage::disk('local')->assertExists($dismantle->photo);

        $this->assertSame((string) $service->user->phone, $gateway->phone);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $service->user_id,
            'type' => ServiceDismantledNotification::class,
        ]);
    }

    public function test_technician_who_is_not_assigned_cannot_complete(): void
    {
        Storage::fake('local');
        $service = $this->queuedForDismantleService();
        $assigned = $this->withRole('technician');
        $other = $this->withRole('technician');
        $this->actingAs($assigned)->post("/dismantles/{$service->id}/claim");

        $response = $this->actingAs($other)->post("/dismantles/{$service->id}/complete", [
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertForbidden();
    }

    public function test_completion_requires_photo(): void
    {
        $service = $this->queuedForDismantleService();
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/dismantles/{$service->id}/claim");

        $response = $this->actingAs($technician)->post("/dismantles/{$service->id}/complete", []);

        $response->assertSessionHasErrors(['photo']);
    }

    public function test_superadmin_cannot_claim(): void
    {
        $service = $this->queuedForDismantleService();
        $superadmin = $this->superadmin();

        // Klaim tetap aksi fieldwork khusus technician — superadmin sengaja
        // tidak ikut kebagian walau punya seluruh permission lain (lihat
        // CLAUDE.md "Authorization / Role & Permission").
        $this->actingAs($superadmin)->post("/dismantles/{$service->id}/claim")->assertForbidden();
    }

    public function test_superadmin_can_complete_dismantle_via_override(): void
    {
        $service = $this->queuedForDismantleService();
        $superadmin = $this->superadmin();

        $service->update(['status' => Service::STATUS_DISMANTLING]);
        ServiceDismantle::where('service_id', $service->id)->firstOrFail()->update([
            'technician_id' => $this->withRole('technician')->id,
        ]);

        // dismantles.complete-any: jalur darurat kalau teknisi yang
        // di-assign resign/tidak aktif — menutup gap "job stuck permanen"
        // (lihat CLAUDE.md "Authorization / Role & Permission").
        $response = $this->actingAs($superadmin)->post("/dismantles/{$service->id}/complete", [
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $response->assertRedirect(route('dismantles.show', $service));
        $this->assertSame(Service::STATUS_DISMANTLED, $service->fresh()->status);
    }

    public function test_non_superadmin_non_technician_roles_forbidden_from_dismantle_routes(): void
    {
        $service = $this->queuedForDismantleService();

        foreach (['finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/dismantles')->assertForbidden();
            $this->actingAs($staff)->get("/dismantles/{$service->id}")->assertForbidden();
        }
    }

    public function test_index_and_show_render_for_superadmin_and_technician(): void
    {
        $service = $this->queuedForDismantleService();

        $this->actingAs($this->superadmin())->get('/dismantles')->assertOk();
        $this->actingAs($this->superadmin())->get("/dismantles/{$service->id}")->assertOk();

        $technician = $this->withRole('technician');
        $this->actingAs($technician)->get('/dismantles')->assertOk();
        $this->actingAs($technician)->get("/dismantles/{$service->id}")->assertOk();
    }

    public function test_dismantle_photo_accessible_by_assigned_technician_and_superadmin_only(): void
    {
        Storage::fake('local');
        $service = $this->queuedForDismantleService();
        $technician = $this->withRole('technician');
        $other = $this->withRole('technician');
        $this->actingAs($technician)->post("/dismantles/{$service->id}/claim");
        $this->actingAs($technician)->post("/dismantles/{$service->id}/complete", [
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $this->actingAs($technician)->get("/secure/dismantle-photo/{$service->id}")->assertOk();
        $this->actingAs($this->superadmin())->get("/secure/dismantle-photo/{$service->id}")->assertOk();
        $this->actingAs($other)->get("/secure/dismantle-photo/{$service->id}")->assertForbidden();
    }

    public function test_dismantle_photo_returns_404_when_not_yet_uploaded(): void
    {
        $service = $this->queuedForDismantleService();

        $response = $this->actingAs($this->superadmin())->get("/secure/dismantle-photo/{$service->id}");

        $response->assertNotFound();
    }
}
