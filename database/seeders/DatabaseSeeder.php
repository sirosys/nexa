<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PermissionSeeder::class,
            SettingSeeder::class,
            AdminUserSeeder::class,
            TestRoleUserSeeder::class,
            SubdistrictSeeder::class,
            PopCoverageSeeder::class,
            ProductPackageSeeder::class,
        ]);
    }
}
