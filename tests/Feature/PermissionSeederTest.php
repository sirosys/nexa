<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Verifikasi matrix role->permission dari PermissionSeeder (dijalankan
 * otomatis lewat migration 2026_07_13_120000_seed_role_permissions.php,
 * bukan lewat DatabaseSeeder — RefreshDatabase tidak menjalankan seeder)
 * sesuai CLAUDE.md "Authorization / Role & Permission".
 */
class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_has_every_permission(): void
    {
        $superadmin = Role::findByName('superadmin', 'web');

        $this->assertSame(
            Permission::count(),
            $superadmin->permissions()->count()
        );
    }

    /**
     * Role 'sales' dihapus total 2026-07-17 — semua permission registrasi
     * pelanggan yang tadinya eksklusif miliknya (services.create/update,
     * service_orders.create/update, users.complete-kyc) sekarang ikut
     * dimiliki technician & finance juga (lihat CLAUDE.md "Authorization /
     * Role & Permission").
     */
    public function test_technician_permissions_match_matrix(): void
    {
        $expected = [
            'installations.view', 'installations.claim', 'installations.complete',
            'dismantles.view', 'dismantles.claim', 'dismantles.complete',
            'tickets.view', 'tickets.claim', 'tickets.resolve',
            'services.view', 'services.create', 'services.update',
            'service_orders.view', 'service_orders.create', 'service_orders.update',
            'users.complete-kyc',
        ];

        $this->assertSameArrays($expected, $this->permissionsFor('technician'));
    }

    /**
     * 'finance' diperluas jadi peran operator harian "Admin/NOC" 2026-07-23
     * — lihat CLAUDE.md "Authorization / Role & Permission" bullet
     * "`finance` diperluas jadi kapabel 'Admin/NOC'".
     */
    public function test_finance_permissions_match_matrix(): void
    {
        $expected = [
            'service_orders.view', 'service_orders.retry-receipt', 'service_orders.create', 'service_orders.update',
            'services.view', 'services.create', 'services.update',
            'users.view', 'users.complete-kyc',
            'installations.view', 'installations.assign', 'installations.complete-any',
            'dismantles.view', 'dismantles.queue', 'dismantles.assign', 'dismantles.complete-any',
            'tickets.view', 'tickets.create', 'tickets.assign', 'tickets.resolve-any',
            'sites.view', 'coverages.view',
            'reports.view',
        ];

        $this->assertSameArrays($expected, $this->permissionsFor('finance'));
    }

    public function test_customer_has_no_permission(): void
    {
        $this->assertSame([], $this->permissionsFor('customer'));
    }

    private function permissionsFor(string $role): array
    {
        return Role::findByName($role, 'web')->permissions->pluck('name')->all();
    }

    private function assertSameArrays(array $expected, array $actual): void
    {
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }
}
