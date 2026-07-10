<?php

namespace Database\Seeders;

use App\Models\Coverage;
use App\Models\Package;
use App\Models\Service;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::role('superadmin')->value('id');
        $customers = User::role('customer')->inRandomOrder()->limit(10)->get();
        $coverageIds = Coverage::pluck('id');
        // Wajib paket is_starter=true — satu-satunya paket yang boleh
        // dipilih saat mendaftarkan service baru (lihat ServiceRequest).
        $starterPackageIds = Package::where('is_starter', true)->pluck('id');
        $subdistrictIds = Subdistrict::inRandomOrder()->limit(10)->pluck('id');

        $customers->values()->each(function (User $customer, int $index) use ($adminId, $coverageIds, $starterPackageIds, $subdistrictIds) {
            Service::factory()->create([
                'user_id' => $customer->id,
                'coverage_id' => $coverageIds->random(),
                'package_id' => $starterPackageIds->random(),
                'subdistrict_id' => $subdistrictIds[$index] ?? $subdistrictIds->random(),
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        });
    }
}
