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
        'settings' => ['view', 'update'],
        'audit_logs' => ['view'],
        'reports' => ['view'],
        'roles' => ['view', 'create', 'update', 'delete'],
    ];

    /**
     * Permission per role selain superadmin (yang selalu dapat semua
     * permission — di-assign terpisah di bawah, bukan didaftarkan di sini,
     * supaya permission baru otomatis ikut tanpa perlu update daftar ini).
     */
    private const ROLE_PERMISSIONS = [
        // Role 'sales' sudah dihapus total (2026-07-17) — semua role staff
        // yang tersisa (di luar superadmin) sekarang ikut dapat permission
        // registrasi pelanggan (services.*/sales.*/users.complete-kyc) di
        // bawah ini, bukan cuma dimiliki satu role eksklusif. Lihat
        // CLAUDE.md "Authorization / Role & Permission".
        'technician' => [
            'installations.view', 'installations.claim', 'installations.complete',
            'dismantles.view', 'dismantles.claim', 'dismantles.complete',
            'tickets.view', 'tickets.claim', 'tickets.resolve',
            'services.view', 'services.create', 'services.update',
            'sales.view', 'sales.create', 'sales.update',
            'users.complete-kyc',
        ],
        // 'finance' diperluas jadi peran operator harian "Admin/NOC" (2026-07-23)
        // — identifier database TETAP 'finance' (dirujuk literal di banyak
        // test & label map), cuma cakupan permission-nya yang diperluas dan
        // label UI-nya diganti "Admin/NOC". Sebelumnya cuma pegang
        // sales.*/services.*/users.complete-kyc (murni transaksi), sekarang
        // ditambah akses dispatch operasional (assign/queue/resolve-any)
        // supaya NOC tidak wajib pakai akun superadmin untuk kerja
        // sehari-hari. Lihat CLAUDE.md "Authorization / Role & Permission".
        'finance' => [
            'sales.view', 'sales.retry-receipt', 'sales.create', 'sales.update',
            'services.view', 'services.create', 'services.update',
            'users.view', 'users.complete-kyc',
            'installations.view', 'installations.assign', 'installations.complete-any',
            'dismantles.view', 'dismantles.queue', 'dismantles.assign', 'dismantles.complete-any',
            'tickets.view', 'tickets.create', 'tickets.assign', 'tickets.resolve-any',
            'sites.view', 'coverages.view',
            'reports.view',
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
            $roleModel = Role::findByName($role, 'web');

            // Kalau permission role ini sudah pernah diedit manual lewat UI
            // /roles (RoleService::updatePermissions() mematikan flag ini
            // begitu superadmin menyimpan perubahan untuk role bawaan),
            // JANGAN ditimpa balik ke matrix hardcoded di sini — role itu
            // sudah lepas dari sinkronisasi otomatis, dikelola penuh lewat
            // UI. Lihat CLAUDE.md "Authorization / Role & Permission".
            if ($roleModel->permissions_managed_by_seeder) {
                $roleModel->syncPermissions($permissions);
            }
        }

        // Superadmin selalu dapat SEMUA permission, diambil dinamis (bukan
        // daftar hardcoded) supaya permission baru dari modul berikutnya
        // otomatis ikut tanpa risiko lupa update daftar ini. Tidak digerbang
        // oleh permissions_managed_by_seeder — superadmin sengaja tidak
        // pernah bisa diedit lewat UI /roles sama sekali.
        Role::findByName('superadmin', 'web')->syncPermissions(Permission::all());
    }
}
