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
    private const USERS = [
        'technician' => ['phone' => '6280000000002', 'name' => 'Teknisi Uji Coba', 'email' => 'technician@nexa.test'],
        'finance' => ['phone' => '6280000000003', 'name' => 'Finance Uji Coba', 'email' => 'finance@nexa.test'],
        'sales' => ['phone' => '6280000000004', 'name' => 'Sales Uji Coba', 'email' => 'sales@nexa.test'],
    ];

    public function run(): void
    {
        foreach (self::USERS as $role => $data) {
            $user = User::query()->updateOrCreate(
                ['phone' => $data['phone']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles([$role]);
        }
    }
}
