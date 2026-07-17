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
    private const ROLES = ['superadmin', 'technician', 'finance', 'customer'];

    public function run(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }
}
