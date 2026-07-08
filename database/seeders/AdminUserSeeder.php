<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['phone' => '6281234567890'],
            [
                'name' => 'Admin NEXA',
                'email' => 'admin@nexa.test',
                'password' => Hash::make('password'),
                'admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
