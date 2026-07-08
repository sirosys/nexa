<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    // Male, born 17 Jan 1990.
    private const NIK_MALE = '3201011701900001';

    // Female, born 5 May 1995.
    private const NIK_FEMALE = '3201014505950002';

    private function admin(): User
    {
        return User::factory()->create(['admin' => true]);
    }

    public function test_admin_can_create_customer_with_auto_generated_code_and_nik_derived_fields(): void
    {
        $response = $this->actingAs($this->admin())->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'nik' => self::NIK_MALE,
        ]);

        $response->assertRedirect(route('users.index'));

        $customer = User::where('phone', '6281234567890')->firstOrFail();
        $this->assertFalse((bool) $customer->admin);
        $this->assertNotNull($customer->code);
        $this->assertDatabaseHas('user_details', [
            'id' => $customer->id,
            'nik' => self::NIK_MALE,
            'gender' => 'male',
            'birth_date' => '1990-01-17',
        ]);
    }

    public function test_admin_can_create_staff_account_without_customer_code(): void
    {
        $response = $this->actingAs($this->admin())->post('/users', [
            'name' => 'Teknisi Jaya',
            'phone' => '81355556666',
            'admin' => '1',
        ]);

        $response->assertRedirect(route('users.index'));

        $staff = User::where('phone', '6281355556666')->firstOrFail();
        $this->assertTrue((bool) $staff->admin);
        $this->assertNull($staff->code);
    }

    public function test_listing_shows_both_customer_and_staff_accounts(): void
    {
        $admin = $this->admin();
        User::factory()->create(['admin' => true, 'name' => 'Staff Satu']);
        User::factory()->create(['admin' => false, 'name' => 'Pelanggan Satu']);

        $response = $this->actingAs($admin)->get('/users');

        $response->assertOk();
        $response->assertSee('Staff Satu');
        $response->assertSee('Pelanggan Satu');
    }

    public function test_admin_cannot_remove_own_admin_flag(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->put("/users/{$admin->id}", [
            'name' => $admin->name,
            'phone' => (string) $admin->phone,
            // admin tidak dikirim -> checkbox unchecked -> mencoba cabut sendiri
        ]);

        $response->assertSessionHasErrors('admin');
        $this->assertTrue((bool) $admin->fresh()->admin);
    }

    public function test_invalid_nik_is_rejected(): void
    {
        $response = $this->actingAs($this->admin())->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            // Bulan 13 — tidak valid.
            'nik' => '3201011713000099',
        ]);

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseMissing('users', ['phone' => '6281234567890']);
    }

    public function test_nik_can_be_set_on_first_update_when_not_provided_at_creation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
        ]);
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $response = $this->actingAs($admin)->put("/users/{$customer->id}", [
            'name' => 'Budi Santoso Update',
            'phone' => '81234567890',
            'nik' => self::NIK_MALE,
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertSame('Budi Santoso Update', $customer->fresh()->name);
        $this->assertDatabaseHas('user_details', [
            'id' => $customer->id,
            'nik' => self::NIK_MALE,
            'gender' => 'male',
            'birth_date' => '1990-01-17',
        ]);
    }

    public function test_nik_is_locked_after_first_save(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'nik' => self::NIK_MALE,
        ]);
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $response = $this->actingAs($admin)->put("/users/{$customer->id}", [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'nik' => self::NIK_FEMALE,
        ]);

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseHas('user_details', [
            'id' => $customer->id,
            'nik' => self::NIK_MALE,
            'gender' => 'male',
        ]);
    }

    public function test_admin_can_delete_customer(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
        ]);
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $response = $this->actingAs($admin)->delete("/users/{$customer->id}");

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseMissing('user_details', ['id' => $customer->id]);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $admin = $this->admin();
        User::factory()->create(['phone' => '6281234567890']);

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_duplicate_nik_is_rejected(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'nik' => self::NIK_MALE,
        ]);

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Lain Orang',
            'phone' => '81299999999',
            'nik' => self::NIK_MALE,
        ]);

        $response->assertSessionHasErrors('nik');
    }

    public function test_non_admin_cannot_access_user_routes(): void
    {
        $customer = User::factory()->create(['admin' => false]);

        $this->actingAs($customer)->get('/users')->assertForbidden();
        $this->actingAs($customer)->post('/users', [
            'name' => 'Test',
            'phone' => '81234567890',
        ])->assertForbidden();
    }

    public function test_ktp_photo_is_stored_privately_and_owner_can_view_it(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        Storage::disk('local')->assertExists($customer->userDetails->ktp_photo);

        $response = $this->actingAs($customer)->get(route('secure.ktp', $customer));
        $response->assertOk();
    }

    public function test_other_non_admin_cannot_view_someone_elses_ktp_photo(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);
        $owner = User::where('phone', '6281234567890')->firstOrFail();
        $stranger = User::factory()->create(['admin' => false]);

        $this->actingAs($stranger)->get(route('secure.ktp', $owner))->assertForbidden();
    }

    public function test_admin_can_view_any_ktp_photo(): void
    {
        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);
        $owner = User::where('phone', '6281234567890')->firstOrFail();

        $this->actingAs($admin)->get(route('secure.ktp', $owner))->assertOk();
    }
}
