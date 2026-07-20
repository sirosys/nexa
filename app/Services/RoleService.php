<?php

namespace App\Services;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use RuntimeException;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function create(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
            // Role custom tidak pernah dirujuk PermissionSeeder::ROLE_PERMISSIONS
            // (matrix itu cuma mengenal 3 nama role bawaan literal), jadi
            // flag ini inert secara fungsional untuk role custom — diisi
            // false eksplisit sekadar supaya jelas dibaca dari tabel
            // (true seharusnya cuma berarti "salah satu dari 4 role bawaan
            // yang masih diikuti default sistem").
            'permissions_managed_by_seeder' => false,
        ]);

        $permissions = $data['permissions'] ?? [];
        $role->syncPermissions($permissions);

        $this->auditLogService->record(
            'role.created',
            $role,
            "Membuat role baru \"{$role->name}\" dengan ".count($permissions).' permission.',
        );

        return $role;
    }

    /**
     * Superadmin selalu ditolak di sini (dikelola PermissionSeeder secara
     * dinamis & tidak pernah diedit lewat UI). Untuk role bawaan lain
     * (technician/finance/customer), nama TIDAK pernah diubah (dirujuk
     * literal di banyak tempat lewat hasRole()/isTechnician()/dst) tapi
     * permission-nya boleh — begitu disimpan, flag
     * permissions_managed_by_seeder dimatikan supaya PermissionSeeder::run()
     * berikutnya (dipanggil ulang tiap modul baru menambah permission) tidak
     * menimpa balik ke matrix hardcoded. Lihat CLAUDE.md "Authorization /
     * Role & Permission".
     */
    public function updatePermissions(Role $role, array $data): Role
    {
        if ($role->name === 'superadmin') {
            throw new RuntimeException('Role Superadmin selalu memiliki seluruh permission secara otomatis dan tidak bisa diubah.');
        }

        $isBuiltInRole = in_array($role->name, RoleSeeder::ROLES, true);

        $oldPermissions = $role->permissions()->pluck('name')->sort()->values()->all();

        if (! $isBuiltInRole && ($data['name'] ?? $role->name) !== $role->name) {
            $role->update(['name' => $data['name']]);
        }

        if ($isBuiltInRole) {
            $role->update(['permissions_managed_by_seeder' => false]);
        }

        $permissions = $data['permissions'] ?? [];
        $role->syncPermissions($permissions);

        $newPermissions = $role->permissions()->pluck('name')->sort()->values()->all();

        if ($oldPermissions !== $newPermissions) {
            $this->auditLogService->record(
                'role.permissions_updated',
                $role,
                "Mengubah permission role \"{$role->name}\".",
                ['from' => $oldPermissions, 'to' => $newPermissions],
            );
        }

        return $role->fresh();
    }

    /**
     * Cuma berlaku untuk role bawaan selain Superadmin — mengembalikan
     * permission-nya ke matrix hardcoded PermissionSeeder::ROLE_PERMISSIONS
     * (mis. setelah superadmin menyesal menyesuaikan permission technician
     * lewat UI). Memanggil ulang PermissionSeeder::run() sekalian (bukan
     * cuma syncPermissions manual) supaya tetap satu sumber kebenaran untuk
     * isi matrix-nya, dan aman idempoten dipanggil kapan saja.
     */
    public function resetToSeederDefault(Role $role): Role
    {
        if ($role->name === 'superadmin' || ! in_array($role->name, RoleSeeder::ROLES, true)) {
            throw new RuntimeException('Cuma role bawaan (selain Superadmin) yang bisa direset ke default.');
        }

        $role->update(['permissions_managed_by_seeder' => true]);

        (new PermissionSeeder)->run();

        $role = $role->fresh();

        $this->auditLogService->record(
            'role.permissions_reset',
            $role,
            "Mengembalikan permission role \"{$role->name}\" ke default sistem.",
        );

        return $role;
    }

    /**
     * Role bawaan sistem tidak pernah bisa dihapus (dirujuk literal di
     * banyak tempat — hasRole('technician'), isCustomer(), seeder, test).
     * Role custom yang masih dipakai user juga ditolak — Spatie
     * model_has_roles.role_id CASCADE ON DELETE, jadi tanpa guard ini
     * menghapus role diam-diam mencabutnya dari semua user yang memilikinya.
     */
    public function delete(Role $role): void
    {
        if (in_array($role->name, RoleSeeder::ROLES, true)) {
            throw new RuntimeException('Role bawaan sistem tidak bisa dihapus.');
        }

        if (User::role($role->name)->exists()) {
            throw new RuntimeException('Role ini masih dipakai oleh pengguna, tidak bisa dihapus.');
        }

        $name = $role->name;
        $role->delete();

        $this->auditLogService->record('role.deleted', null, "Menghapus role \"{$name}\".");
    }
}
