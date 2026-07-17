<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Data migration — role 'sales' dihapus total (2026-07-17, permintaan
     * eksplisit user): semua role staff yang tersisa sekarang ikut bisa
     * mendaftarkan pelanggan baru, jadi tidak ada lagi kebutuhan role
     * eksklusif "sales" (lihat CLAUDE.md "Authorization / Role & Permission").
     * User yang masih memegang role 'sales' (kalau ada) dipindah ke
     * 'finance' dulu sebelum role-nya dihapus, supaya tidak ada akun yang
     * tiba-tiba kehilangan seluruh permission. Baris `roles`/`model_has_roles`/
     * `role_has_permissions` yang terkait 'sales' ikut terhapus lewat
     * cascadeOnDelete (lihat migration create_permission_tables).
     */
    public function up(): void
    {
        $salesRole = Role::where('name', 'sales')->where('guard_name', 'web')->first();

        if ($salesRole !== null) {
            User::role('sales')->get()->each(fn (User $user) => $user->syncRoles(['finance']));

            $salesRole->delete();
        }

        // Matrix permission technician/finance sudah diperluas (services.*/
        // sales.*/users.complete-kyc) — full-sync ulang supaya database yang
        // sudah ada (bukan cuma fresh install) ikut menerima permission baru.
        (new PermissionSeeder)->run();
    }

    public function down(): void
    {
        // Data migration — tidak perlu di-reverse (idempotent, aman dijalankan ulang).
    }
};
