<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Katalog permission granular per-aksi, dikelompokkan per modul.
     * Lihat CLAUDE.md "Authorization / Role & Permission" untuk rasional
     * tiap permission non-CRUD (mis. *.complete-any, *.resolve-any).
     */
    private const PERMISSIONS = [
        'users' => ['view', 'create', 'update', 'delete', 'complete-kyc', 'view-ktp-photo'],
        'plans' => ['view', 'create', 'update', 'delete'],
        'products' => ['view', 'create', 'update', 'delete'],
        'packages' => ['view', 'create', 'update', 'delete'],
        'sites' => ['view', 'create', 'update', 'delete'],
        'coverages' => ['view', 'create', 'update', 'delete'],
        'services' => ['view', 'create', 'update', 'delete'],
        'installations' => ['view', 'assign', 'claim', 'complete', 'complete-any'],
        'dismantles' => ['view', 'queue', 'assign', 'claim', 'complete', 'complete-any'],
        'sales' => ['view', 'create', 'update', 'delete', 'retry-receipt'],
        'tickets' => ['view', 'create', 'update', 'delete', 'assign', 'claim', 'resolve', 'resolve-any'],
        'inventory' => ['view', 'create', 'delete', 'stock-in', 'adjust'],
        'vendors' => ['view', 'create', 'update', 'delete'],
        'purchase_orders' => ['view', 'create', 'update', 'delete', 'order', 'receive', 'cancel'],
        'settings' => ['view', 'update'],
        'audit_logs' => ['view'],
        'reports' => ['view'],
    ];

    /**
     * Permission per role selain superadmin (yang selalu dapat semua
     * permission — di-assign terpisah di bawah, bukan didaftarkan di sini,
     * supaya permission baru otomatis ikut tanpa perlu update daftar ini).
     */
    private const ROLE_PERMISSIONS = [
        'technician' => [
            'installations.view', 'installations.claim', 'installations.complete',
            'dismantles.view', 'dismantles.claim', 'dismantles.complete',
            'tickets.view', 'tickets.claim', 'tickets.resolve',
            'inventory.view',
        ],
        'finance' => [
            'sales.view', 'sales.retry-receipt',
            'services.view',
        ],
        'sales' => [
            'services.view', 'services.create', 'services.update',
            'sales.view', 'sales.create', 'sales.update',
            'users.complete-kyc',
        ],
        'customer' => [],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$module}.{$action}", 'guard_name' => 'web']);
            }
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            Role::findByName($role, 'web')->syncPermissions($permissions);
        }

        // Superadmin selalu dapat SEMUA permission, diambil dinamis (bukan
        // daftar hardcoded) supaya permission baru dari modul berikutnya
        // otomatis ikut tanpa risiko lupa update daftar ini.
        Role::findByName('superadmin', 'web')->syncPermissions(Permission::all());
    }
}
