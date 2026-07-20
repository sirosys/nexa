<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    // Role 'sales' dihapus total 2026-07-17 — semua role staff yang tersisa
    // sekarang bisa mendaftarkan pelanggan baru (lihat CLAUDE.md
    // "Authorization / Role & Permission"), jadi tidak perlu role
    // eksklusif "sales" lagi.
    //
    // Public (bukan private) — dipakai RoleService/RoleRequest/roles/edit
    // (modul Role & Permission Management di /roles) untuk mengenali "role
    // bawaan sistem" yang tidak boleh di-rename/dihapus lewat UI itu, tanpa
    // duplikasi daftar ini di tempat lain.
    public const ROLES = ['superadmin', 'technician', 'finance', 'customer'];

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
