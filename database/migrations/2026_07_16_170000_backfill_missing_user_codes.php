<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Menutup gap yang ditemukan lewat laporan nyata (`route('users.show', ...)`
 * error "Missing required parameter") — `DatabaseSeeder` pakai trait
 * WithoutModelEvents, jadi hook User::booted() (lihat migration
 * 2026_07_16_160000 & CLAUDE.md "User") yang biasanya mengisi `code`
 * otomatis TIDAK PERNAH fire untuk user hasil `AdminUserSeeder`/
 * `TestRoleUserSeeder`. Kedua seeder itu sudah diperbaiki untuk mengisi
 * `code` manual ke depannya — migration ini cuma membackfill baris yang
 * SUDAH terlanjur null di database yang sudah pernah di-`migrate:fresh
 * --seed` dengan kode lama (sebelum seeder itu diperbaiki).
 */
return new class extends Migration
{
    public function up(): void
    {
        User::whereNull('code')->get()->each(function (User $user) {
            $user->update(['code' => User::generateUniqueCode()]);
        });
    }

    public function down(): void
    {
        // Satu-arah — konsisten migration data lain di project ini.
    }
};
