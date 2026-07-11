<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    // Male, born 17 Jan 1990.
    private const NIK_MALE = '3201011701900001';

    // Female, born 5 May 1995.
    private const NIK_FEMALE = '3201014505950002';

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

    public function test_superadmin_can_create_customer_with_auto_generated_code_and_nik_derived_fields(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            'nik' => self::NIK_MALE,
        ]);

        $response->assertRedirect(route('users.index'));

        $customer = User::where('phone', '6281234567890')->firstOrFail();
        $this->assertTrue($customer->hasRole('customer'));
        $this->assertNotNull($customer->code);
        $this->assertDatabaseHas('user_details', [
            'id' => $customer->id,
            'nik' => self::NIK_MALE,
            'gender' => 'male',
            'birth_date' => '1990-01-17',
        ]);
    }

    public function test_superadmin_can_create_technician_account_without_customer_code(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/users', [
            'name' => 'Teknisi Jaya',
            'phone' => '81355556666',
            'email' => 'teknisi.jaya@example.com',
            'role' => 'technician',
        ]);

        $response->assertRedirect(route('users.index'));

        $staff = User::where('phone', '6281355556666')->firstOrFail();
        $this->assertTrue($staff->hasRole('technician'));
        $this->assertNull($staff->code);
    }

    public function test_listing_shows_accounts_across_roles(): void
    {
        $superadmin = $this->superadmin();
        $this->withRole('technician')->update(['name' => 'Staff Satu']);
        $this->withRole('customer')->update(['name' => 'Pelanggan Satu']);

        $response = $this->actingAs($superadmin)->get('/users');

        $response->assertOk();
        $response->assertSee('Staff Satu');
        $response->assertSee('Pelanggan Satu');
    }

    public function test_superadmin_can_view_user_detail(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->withRole('customer')->fresh();
        $customer->update(['name' => 'Pelanggan Detail']);

        $response = $this->actingAs($superadmin)->get(route('users.show', $customer));

        $response->assertOk();
        $response->assertSee('Pelanggan Detail');
    }

    public function test_superadmin_cannot_change_own_role_away_from_superadmin(): void
    {
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->put("/users/{$superadmin->id}", [
            'name' => $superadmin->name,
            'phone' => (string) $superadmin->phone,
            'email' => $superadmin->email,
            'role' => 'technician',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertTrue($superadmin->fresh()->hasRole('superadmin'));
    }

    public function test_invalid_nik_is_rejected(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            // Bulan 13 — tidak valid.
            'nik' => '3201011713000099',
        ]);

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseMissing('users', ['phone' => '6281234567890']);
    }

    public function test_nik_can_be_set_on_first_update_when_not_provided_at_creation(): void
    {
        $superadmin = $this->superadmin();
        $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
        ]);
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $response = $this->actingAs($superadmin)->put("/users/{$customer->id}", [
            'name' => 'Budi Santoso Update',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
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
        $superadmin = $this->superadmin();
        $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            'nik' => self::NIK_MALE,
        ]);
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $response = $this->actingAs($superadmin)->put("/users/{$customer->id}", [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            'nik' => self::NIK_FEMALE,
        ]);

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseHas('user_details', [
            'id' => $customer->id,
            'nik' => self::NIK_MALE,
            'gender' => 'male',
        ]);
    }

    public function test_superadmin_can_delete_customer(): void
    {
        $superadmin = $this->superadmin();
        $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
        ]);
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $response = $this->actingAs($superadmin)->delete("/users/{$customer->id}");

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseMissing('user_details', ['id' => $customer->id]);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $superadmin = $this->superadmin();
        User::factory()->create(['phone' => '6281234567890']);

        $response = $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_duplicate_nik_is_rejected(): void
    {
        $superadmin = $this->superadmin();
        $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            'nik' => self::NIK_MALE,
        ]);

        $response = $this->actingAs($superadmin)->post('/users', [
            'name' => 'Lain Orang',
            'phone' => '81299999999',
            'email' => 'lain.orang@example.com',
            'role' => 'customer',
            'nik' => self::NIK_MALE,
        ]);

        $response->assertSessionHasErrors('nik');
    }

    public function test_customer_cannot_access_user_routes(): void
    {
        $customer = $this->withRole('customer');

        $this->actingAs($customer)->get('/users')->assertForbidden();
        $this->actingAs($customer)->post('/users', [
            'name' => 'Test',
            'phone' => '81234567890',
            'email' => 'test@example.com',
            'role' => 'customer',
        ])->assertForbidden();
    }

    /**
     * Gate `/users` masih sengaja cuma untuk superadmin — technician/finance/sales
     * belum dapat akses apa pun di iterasi ini (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_staff_roles_cannot_access_user_routes(): void
    {
        foreach (['technician', 'finance', 'sales'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/users')->assertForbidden();
        }
    }

    public function test_ktp_photo_is_stored_privately_and_owner_can_view_it(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
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
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);
        $owner = User::where('phone', '6281234567890')->firstOrFail();
        $stranger = $this->withRole('customer');

        $this->actingAs($stranger)->get(route('secure.ktp', $owner))->assertForbidden();
    }

    public function test_superadmin_can_view_any_ktp_photo(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);
        $owner = User::where('phone', '6281234567890')->firstOrFail();

        $this->actingAs($superadmin)->get(route('secure.ktp', $owner))->assertOk();
    }

    public function test_name_is_normalized_to_title_case(): void
    {
        $this->actingAs($this->superadmin())->post('/users', [
            'name' => 'budi   SANTOSO',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
        ]);

        $customer = User::where('phone', '6281234567890')->firstOrFail();
        $this->assertSame('Budi Santoso', $customer->name);
    }

    public function test_new_user_receives_whatsapp_welcome_notification(): void
    {
        $gateway = $this->fakeGateway();

        $this->actingAs($this->superadmin())->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
        ]);

        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $this->assertSame((string) $customer->phone, $gateway->phone);
        $this->assertStringContainsString('Budi Santoso', $gateway->message);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $customer->id]);
    }

    /**
     * Endpoint dipakai modal "Lengkapi NIK & Foto KTP" di form Service
     * (lihat CLAUDE.md "Service") — dipanggil lewat fetch, bukan form POST
     * biasa, jadi diuji langsung ke endpoint-nya di sini.
     */
    public function test_complete_kyc_endpoint_fills_nik_and_ktp_photo(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();
        $customer = $this->withRole('customer');

        $response = $this->actingAs($superadmin)->postJson("/users/{$customer->id}/complete-kyc", [
            'nik' => self::NIK_MALE,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['id' => $customer->id, 'has_nik' => true, 'has_ktp_photo' => true]);

        $customer->refresh();
        $this->assertSame(self::NIK_MALE, $customer->userDetails->nik);
        $this->assertNotNull($customer->userDetails->ktp_photo);
        Storage::disk('local')->assertExists($customer->userDetails->ktp_photo);
    }

    public function test_complete_kyc_endpoint_rejects_when_nik_already_set(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();
        $customer = $this->withRole('customer');
        $customer->userDetails()->create(['nik' => self::NIK_MALE, 'gender' => 'male', 'birth_date' => '1990-01-17']);

        $response = $this->actingAs($superadmin)->postJson("/users/{$customer->id}/complete-kyc", [
            'nik' => self::NIK_FEMALE,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('nik');
        $this->assertSame(self::NIK_MALE, $customer->fresh()->userDetails->nik);
    }

    public function test_complete_kyc_endpoint_forbidden_for_non_superadmin(): void
    {
        $staff = $this->withRole('technician');
        $customer = $this->withRole('customer');

        $this->actingAs($staff)->postJson("/users/{$customer->id}/complete-kyc", [
            'nik' => self::NIK_MALE,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertForbidden();
    }
}
