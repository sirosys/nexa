<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class SubdistrictSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('subdistricts')->exists()) {
            return;
        }

        DB::unprepared(File::get(database_path('seeders/subdistricts.sql')));
    }
}
