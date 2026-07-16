<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `users.code` sekarang wajib untuk SEMUA role (bukan cuma customer) dan
 * berformat 6 karakter alphanumeric acak (bukan lagi 'CUS'+id) — dipakai
 * sebagai route key URL /users/{user} supaya id database tidak bocor lewat
 * URL. Lihat CLAUDE.md "User". Migration data satu-arah (bukan add/drop
 * kolom — skema `code` sudah ada sejak awal), regenerate SEMUA baris
 * (termasuk customer lama yang masih format CUS+id) supaya konsisten satu
 * format di seluruh tabel.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->select('id')->orderBy('id')->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update([
                'code' => User::generateUniqueCode(),
            ]);
        });
    }

    public function down(): void
    {
        // Satu-arah — format lama (CUS+id) tidak berarti untuk di-restore,
        // konsisten pola migration data lain di project ini.
    }
};
