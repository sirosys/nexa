<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const ROLES = ['superadmin', 'technician', 'finance', 'sales', 'customer'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::ROLES as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        // Kolom `admin` masih ada di titik ini (baru di-drop di migration
        // berikutnya) — dibaca lewat query builder mentah supaya tidak
        // bergantung pada cast/fillable Eloquent yang sudah tidak lagi
        // menyertakan kolom ini di kode aplikasi.
        DB::table('users')->select('id', 'admin')->orderBy('id')->each(function ($row) {
            $user = User::find($row->id);
            $user->assignRole($row->admin ? 'superadmin' : 'customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data migration — tidak perlu di-reverse (kolom admin ikut kembali
        // lewat down() migration drop_admin_from_users_table).
    }
};
