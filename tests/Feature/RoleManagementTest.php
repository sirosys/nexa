<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\Support\GeneratesValidNik;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use GeneratesValidNik;
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_superadmin_can_view_roles_index(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/roles');

        $response->assertOk();
        $response->assertSee('superadmin');
        $response->assertSee('technician');
    }

    public function test_superadmin_can_create_a_custom_role(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/roles', [
            'name' => 'warehouse_staff',
        ]);

        $role = Role::where('name', 'warehouse_staff')->firstOrFail();

        $response->assertRedirect(route('roles.edit', $role));
        $this->assertFalse((bool) $role->permissions_managed_by_seeder);
        $this->assertCount(0, $role->permissions);
    }

    public function test_superadmin_can_assign_permissions_to_a_custom_role(): void
    {
        $admin = $this->superadmin();
        $role = Role::create(['name' => 'warehouse_staff', 'guard_name' => 'web', 'permissions_managed_by_seeder' => false]);

        $response = $this->actingAs($admin)->put("/roles/{$role->id}", [
            'name' => 'warehouse_staff',
            'permissions' => ['sites.view', 'coverages.view'],
        ]);

        $response->assertRedirect(route('roles.edit', $role));
        $role->refresh();
        $this->assertEqualsCanonicalizing(['sites.view', 'coverages.view'], $role->permissions->pluck('name')->all());

        $log = AuditLog::where('action', 'role.permissions_updated')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertEqualsCanonicalizing(['sites.view', 'coverages.view'], $log->changes['to']);
    }

    public function test_resubmitting_the_same_permission_set_does_not_record_a_new_audit_entry(): void
    {
        $admin = $this->superadmin();
        $role = Role::create(['name' => 'warehouse_staff', 'guard_name' => 'web', 'permissions_managed_by_seeder' => false]);
        $role->syncPermissions(['sites.view']);

        $this->actingAs($admin)->put("/roles/{$role->id}", [
            'name' => 'warehouse_staff',
            'permissions' => ['sites.view'],
        ]);

        $this->assertSame(0, AuditLog::where('action', 'role.permissions_updated')->count());
    }

    public function test_superadmin_can_rename_a_custom_role(): void
    {
        $role = Role::create(['name' => 'warehouse_staff', 'guard_name' => 'web', 'permissions_managed_by_seeder' => false]);

        $this->actingAs($this->superadmin())->put("/roles/{$role->id}", [
            'name' => 'gudang_staff',
            'permissions' => [],
        ]);

        $this->assertSame('gudang_staff', $role->fresh()->name);
    }

    /**
     * Bukti inti fitur ini dibangun: editan superadmin ke permission
     * technician lewat UI TIDAK ditimpa balik begitu PermissionSeeder::run()
     * dipanggil lagi (simulasi migration modul baru menambah permission).
     */
    public function test_editing_a_built_in_role_survives_a_later_permission_seeder_run(): void
    {
        $admin = $this->superadmin();
        $technician = Role::findByName('technician', 'web');
        $originalPermissions = $technician->permissions()->pluck('name')->sort()->values()->all();

        $this->assertNotContains('reports.view', $originalPermissions);

        $this->actingAs($admin)->put("/roles/{$technician->id}", [
            'permissions' => ['reports.view'],
        ]);

        $technician->refresh();
        $this->assertFalse((bool) $technician->permissions_managed_by_seeder);
        $this->assertEqualsCanonicalizing(['reports.view'], $technician->permissions->pluck('name')->all());

        // Simulasikan migration modul baru berikutnya memanggil ulang
        // PermissionSeeder::run() (mis. menambah permission baru ke katalog).
        (new PermissionSeeder)->run();

        $technician->refresh();
        $this->assertEqualsCanonicalizing(['reports.view'], $technician->permissions->pluck('name')->all());
    }

    public function test_built_in_roles_cannot_be_renamed_or_deleted(): void
    {
        $admin = $this->superadmin();
        $finance = Role::findByName('finance', 'web');

        $this->actingAs($admin)->put("/roles/{$finance->id}", [
            'name' => 'renamed_finance',
            'permissions' => ['service_orders.view'],
        ]);
        $this->assertSame('finance', $finance->fresh()->name);

        $response = $this->actingAs($admin)->delete("/roles/{$finance->id}");
        $response->assertRedirect(route('roles.index'));
        $response->assertSessionHas('error');
        $this->assertNotNull(Role::find($finance->id));
    }

    public function test_superadmin_permissions_cannot_be_edited(): void
    {
        $admin = $this->superadmin();
        $superadminRole = Role::findByName('superadmin', 'web');
        $before = $superadminRole->permissions()->pluck('name')->sort()->values()->all();

        $response = $this->actingAs($admin)->put("/roles/{$superadminRole->id}", [
            'permissions' => ['users.view'],
        ]);

        $response->assertSessionHas('error');
        $this->assertSame($before, $superadminRole->fresh()->permissions()->pluck('name')->sort()->values()->all());
    }

    public function test_reset_to_default_restores_hardcoded_permissions_and_reenables_flag(): void
    {
        $admin = $this->superadmin();
        $technician = Role::findByName('technician', 'web');
        $originalPermissions = $technician->permissions()->pluck('name')->sort()->values()->all();

        $this->actingAs($admin)->put("/roles/{$technician->id}", [
            'permissions' => ['reports.view'],
        ]);

        $response = $this->actingAs($admin)->post("/roles/{$technician->id}/reset-to-default");

        $response->assertRedirect(route('roles.edit', $technician));
        $technician->refresh();
        $this->assertTrue((bool) $technician->permissions_managed_by_seeder);
        $this->assertEqualsCanonicalizing($originalPermissions, $technician->permissions->pluck('name')->all());
    }

    public function test_deleting_a_custom_role_with_no_users_succeeds(): void
    {
        $role = Role::create(['name' => 'warehouse_staff', 'guard_name' => 'web', 'permissions_managed_by_seeder' => false]);

        $response = $this->actingAs($this->superadmin())->delete("/roles/{$role->id}");

        $response->assertRedirect(route('roles.index'));
        $this->assertNull(Role::find($role->id));
    }

    public function test_deleting_a_custom_role_with_assigned_users_is_rejected(): void
    {
        $role = Role::create(['name' => 'warehouse_staff', 'guard_name' => 'web', 'permissions_managed_by_seeder' => false]);
        $staff = User::factory()->create();
        $staff->assignRole('warehouse_staff');

        $response = $this->actingAs($this->superadmin())->delete("/roles/{$role->id}");

        $response->assertSessionHas('error');
        $this->assertNotNull(Role::find($role->id));
        $this->assertTrue($staff->fresh()->hasRole('warehouse_staff'));
    }

    public function test_role_name_must_be_unique(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/roles', ['name' => 'technician']);

        $response->assertSessionHasErrors('name');
    }

    public function test_role_name_format_is_validated(): void
    {
        $admin = $this->superadmin();

        $this->actingAs($admin)->post('/roles', ['name' => 'Warehouse Staff'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/roles', ['name' => 'WAREHOUSE'])->assertSessionHasErrors('name');
    }

    public function test_non_superadmin_roles_are_forbidden(): void
    {
        $role = Role::create(['name' => 'warehouse_staff', 'guard_name' => 'web', 'permissions_managed_by_seeder' => false]);

        foreach (['technician', 'finance'] as $roleName) {
            $user = $this->withRole($roleName);

            $this->actingAs($user)->get('/roles')->assertForbidden();
            $this->actingAs($user)->post('/roles', ['name' => 'another_role'])->assertForbidden();
            $this->actingAs($user)->get("/roles/{$role->id}/edit")->assertForbidden();
            $this->actingAs($user)->put("/roles/{$role->id}", ['name' => 'warehouse_staff', 'permissions' => []])->assertForbidden();
            $this->actingAs($user)->delete("/roles/{$role->id}")->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/roles')->assertRedirect('/login');
    }

    public function test_new_custom_role_is_selectable_when_creating_a_user(): void
    {
        $admin = $this->superadmin();
        Role::create(['name' => 'warehouse_staff', 'guard_name' => 'web', 'permissions_managed_by_seeder' => false]);

        // Muncul di modal "Tambah Pengguna" (select role) di /users.
        $response = $this->actingAs($admin)->get('/users');
        $response->assertOk();
        $response->assertSee('warehouse_staff');

        // Lolos validasi UserRequest (Rule::in(Role::pluck('name')) dinamis)
        // dan benar-benar tersimpan lewat endpoint store sungguhan.
        $newUser = User::factory()->make();
        $this->actingAs($admin)->post('/users', [
            'name' => $newUser->name,
            'phone' => (string) $newUser->phone,
            'email' => $newUser->email,
            'role' => 'warehouse_staff',
            'nik' => $this->validNik(),
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertRedirect(route('users.index'));

        $created = User::where('email', $newUser->email)->first();
        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('warehouse_staff'));
    }
}
