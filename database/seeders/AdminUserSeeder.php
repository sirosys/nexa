<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // DatabaseSeeder pakai trait WithoutModelEvents — hook
        // User::booted() (static::creating(), lihat CLAUDE.md "User") yang
        // biasanya mengisi `code` otomatis TIDAK PERNAH fire selama proses
        // seeding. `code` jadi wajib dihitung manual di sini, pola sama
        // seeder lain (mis. ProductPackageSeeder) yang juga tidak bisa
        // mengandalkan efek samping otomatis di konteks seeder/console.
        $existing = User::where('phone', '6280000000001')->first();

        $user = User::query()->updateOrCreate(
            ['phone' => '6280000000001'],
            [
                'name' => 'Admin NEXA',
                'email' => 'sirosys.id@gmail.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'code' => $existing?->code ?? User::generateUniqueCode(),
            ]
        );

        $user->syncRoles(['superadmin']);
    }
}
