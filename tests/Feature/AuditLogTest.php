<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Subdistrict;
use App\Models\User;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CapturingWhatsappGateway;
use Tests\Support\GeneratesValidNik;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use GeneratesValidNik, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Subdistrict::factory()->create(['district_id' => 320101]);
    }

    private const NIK_MALE = '3201011701900001';

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

    public function test_role_change_creates_audit_log_entry_with_old_and_new_role(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->withRole('customer');

        $this->actingAs($superadmin)->put(route('users.update', $customer), [
            'name' => $customer->name,
            'phone' => (string) $customer->phone,
            'email' => $customer->email,
            'role' => 'technician',
        ]);

        $log = AuditLog::where('action', 'user.role_changed')->firstOrFail();
        $this->assertSame($superadmin->id, $log->actor_id);
        $this->assertSame(User::class, $log->auditable_type);
        $this->assertSame($customer->id, $log->auditable_id);
        // MySQL JSON tidak menjaga urutan key insertion, jadi cek per-key
        // (bukan assertSame array penuh) supaya tidak rapuh terhadap urutan.
        $this->assertSame('customer', $log->changes['from']);
        $this->assertSame('technician', $log->changes['to']);
    }

    public function test_resubmitting_same_role_does_not_create_audit_log_entry(): void
    {
        $superadmin = $this->superadmin();
        $technician = $this->withRole('technician');

        $this->actingAs($superadmin)->put(route('users.update', $technician), [
            'name' => $technician->name,
            'phone' => (string) $technician->phone,
            'email' => $technician->email,
            'role' => 'technician',
        ]);

        $this->assertSame(0, AuditLog::where('action', 'user.role_changed')->count());
    }

    public function test_creating_and_deleting_user_creates_audit_log_entries(): void
    {
        Storage::fake('local');
        $this->fakeGateway();
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            'nik' => self::NIK_MALE,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $customer = User::where('phone', '6281234567890')->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'actor_id' => $superadmin->id,
            'auditable_id' => $customer->id,
        ]);

        $this->actingAs($superadmin)->delete(route('users.destroy', $customer));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deleted',
            'actor_id' => $superadmin->id,
        ]);
    }

    private function settingsPayload(array $valuesByKey): array
    {
        $ids = Setting::query()->pluck('id', 'key');

        $settings = [];
        foreach ($valuesByKey as $key => $value) {
            $settings[$ids[$key]] = $value;
        }

        return ['settings' => $settings];
    }

    public function test_updating_settings_creates_audit_log_entry_only_for_changed_values(): void
    {
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->put('/settings', $this->settingsPayload([
            // Nilai default billing.invoice_ttl_days = 3 (lihat SettingSeeder) — sengaja
            // dikirim ulang tanpa berubah, dismantle.suspended_months_threshold diubah.
            'billing.invoice_ttl_days' => 3,
            'renewal.remind_days_before.invoice' => 5,
            'renewal.remind_days_before.h3' => 3,
            'renewal.remind_days_before.h1' => 1,
            'dismantle.suspended_months_threshold' => 4,
        ]));

        // Cuma dismantle.suspended_months_threshold yang benar-benar berubah
        // (2 -> 4) — billing.invoice_ttl_days dikirim ulang dengan nilai
        // yang sama (3), jadi TIDAK boleh menghasilkan entry audit log.
        $this->assertSame(1, AuditLog::where('action', 'settings.updated')->count());

        $log = AuditLog::where('action', 'settings.updated')->firstOrFail();
        $this->assertSame($superadmin->id, $log->actor_id);
        $this->assertSame('2', $log->changes['from']);
        $this->assertSame('4', $log->changes['to']);
    }

    /**
     * Service pending_installation + Sale registrasi settled — prasyarat
     * yang sama dipakai InstallationManagementTest.
     */
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

    public function test_completing_installation_creates_audit_log_entry(): void
    {
        Storage::fake('local');
        $this->fakeGateway();
        $service = $this->pendingInstallationService();
        $technician = $this->withRole('technician');
        $this->actingAs($technician)->post("/installations/{$service->id}/claim");

        $this->actingAs($technician)->post("/installations/{$service->id}/complete", [
            'odp_port' => 'ODP-01-02',
            'photo' => UploadedFile::fake()->image('bukti.jpg'),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service.activated',
            'actor_id' => $technician->id,
            'auditable_type' => Service::class,
            'auditable_id' => $service->id,
        ]);
    }

    /**
     * Bukti pola actor null-untuk-sistem: renewal:suspend-overdue dijalankan
     * lewat scheduler console, tidak ada sesi login sama sekali.
     */
    public function test_scheduler_triggered_suspend_creates_audit_log_entry_with_null_actor(): void
    {
        Notification::fake();

        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'expired_at' => now()->subDay(),
        ]);

        Artisan::call('renewal:suspend-overdue');

        $log = AuditLog::where('action', 'service.suspended')->firstOrFail();
        $this->assertNull($log->actor_id);
        $this->assertSame($service->id, $log->auditable_id);
    }

    public function test_superadmin_can_view_and_filter_audit_log_index(): void
    {
        AuditLog::query()->create([
            'actor_id' => null,
            'action' => 'service.suspended',
            'description' => 'Layanan SRV000001 disuspend otomatis karena telat bayar.',
        ]);

        $response = $this->actingAs($this->superadmin())->get('/audit-logs?action=service.suspended');

        $response->assertOk();
        $response->assertSee('service.suspended');
        $response->assertSee('Sistem');
    }

    public function test_non_superadmin_roles_are_forbidden_from_audit_log(): void
    {
        foreach (['technician', 'finance', 'customer'] as $role) {
            $this->actingAs($this->withRole($role))->get('/audit-logs')->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/audit-logs')->assertRedirect('/login');
    }
}
