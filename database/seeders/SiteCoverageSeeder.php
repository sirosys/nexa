<?php

namespace Database\Seeders;

use App\Models\Coverage;
use App\Models\Site;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Database\Seeder;

class SiteCoverageSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::role('superadmin')->value('id');

        // Pakai subdistrict yang sungguhan sudah di-seed (data referensi
        // Kemendagri), bukan Subdistrict::factory() — supaya tidak menambah
        // baris palsu ke tabel referensi read-only.
        $subdistrictIds = Subdistrict::inRandomOrder()->limit(5)->pluck('id');

        $sites = $subdistrictIds->map(fn (int $subdistrictId) => Site::factory()->create([
            'subdistrict_id' => $subdistrictId,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ]));

        foreach (range(1, 5) as $i) {
            Coverage::factory()->create([
                'site_id' => $sites->random()->id,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ]);
        }
    }
}
