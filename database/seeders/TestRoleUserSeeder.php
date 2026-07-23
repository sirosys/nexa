<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestRoleUserSeeder extends Seeder
{
    /**
     * Satu akun uji coba per role selain `superadmin` (sudah disediakan
     * AdminUserSeeder) dan `customer` (tidak pernah login ke NEXA) — nomor
     * telepon berurutan dari superadmin (6280000000001) supaya gampang
     * dihafal staff saat testing manual lintas role.
     */
    // Role 'sales' dihapus total 2026-07-17 (lihat CLAUDE.md "Authorization
    // / Role & Permission") — tidak ada lagi akun uji coba untuk role itu.
    private const USERS = [
        'technician' => ['phone' => '6280000000002', 'name' => 'Teknisi Uji Coba', 'email' => 'technician@nexa.test'],
        'finance' => ['phone' => '6280000000003', 'name' => 'Admin/NOC Uji Coba', 'email' => 'finance@nexa.test'],
    ];

    public function run(): void
    {
        foreach (self::USERS as $role => $data) {
            // DatabaseSeeder pakai trait WithoutModelEvents — hook
            // User::booted() yang biasanya mengisi `code` otomatis tidak
            // pernah fire di sini, jadi dihitung manual (lihat
            // AdminUserSeeder untuk penjelasan lengkap).
            $existing = User::where('phone', $data['phone'])->first();

            $user = User::query()->updateOrCreate(
                ['phone' => $data['phone']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'code' => $existing?->code ?? User::generateUniqueCode(),
                ]
            );

            $user->syncRoles([$role]);
        }
    }
}
