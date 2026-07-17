<?php

namespace Tests\Feature;

use App\Models\Subdistrict;
use App\Models\User;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CapturingWhatsappGateway;
use Tests\Support\GeneratesValidNik;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use GeneratesValidNik, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // NIK_MALE/NIK_FEMALE di bawah berkode wilayah 320101 — beda dari
        // NIK hasil trait GeneratesValidNik::validNik() (yang sudah bikin
        // baris Subdistrict cocok sendiri), dua konstanta hardcoded ini
        // butuh baris Subdistrict yang cocok disiapkan manual supaya lolos
        // guard district_id di App\Rules\ValidNik.
        Subdistrict::factory()->create(['district_id' => 320101]);
    }

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    // Male, born 17 Jan 1990. Kode wilayah 320101 — lihat setUp().
    private const NIK_MALE = '3201011701900001';

    // Female, born 5 May 1995. Kode wilayah 320101 — lihat setUp().
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

    /**
     * Field NIK & foto KTP wajib diisi di form "Tambah Pengguna" (lihat
     * CLAUDE.md "User") — helper ini melengkapi payload create standar
     * supaya tiap test tidak mengulang boilerplate ini sendiri-sendiri.
     */
    private function validCreatePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
            'nik' => $this->validNik(),
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ], $overrides);
    }

    public function test_superadmin_can_create_customer_with_auto_generated_code_and_nik_derived_fields(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->superadmin())->post('/users', $this->validCreatePayload([
            'nik' => self::NIK_MALE,
        ]));

        $response->assertRedirect(route('users.index'));

        $customer = User::where('phone', '6281234567890')->firstOrFail();
        $this->assertTrue($customer->hasRole('customer'));
        $this->assertNotNull($customer->code);
        $this->assertSame(6, strlen($customer->code));
        $this->assertDatabaseHas('user_details', [
            'id' => $customer->id,
            'nik' => self::NIK_MALE,
            'gender' => 'male',
            'birth_date' => '1990-01-17',
        ]);
    }

    /**
     * `code` sekarang digenerate untuk SEMUA role (bukan cuma customer,
     * lihat CLAUDE.md "User") — beda dari perilaku lama.
     */
    public function test_superadmin_can_create_technician_account_with_auto_generated_code(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->superadmin())->post('/users', $this->validCreatePayload([
            'name' => 'Teknisi Jaya',
            'phone' => '81355556666',
            'email' => 'teknisi.jaya@example.com',
            'role' => 'technician',
        ]));

        $response->assertRedirect(route('users.index'));

        $staff = User::where('phone', '6281355556666')->firstOrFail();
        $this->assertTrue($staff->hasRole('technician'));
        $this->assertNotNull($staff->code);
    }

    /**
     * Semua kolom di form "Tambah Pengguna" wajib (lihat CLAUDE.md "User")
     * — NIK & foto KTP tidak lagi opsional saat create, terlepas dari role.
     */
    public function test_nik_and_ktp_photo_are_required_when_creating_a_user(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/users', [
            'name' => 'Budi Santoso',
            'phone' => '81234567890',
            'email' => 'budi.santoso@example.com',
            'role' => 'customer',
        ]);

        $response->assertSessionHasErrors(['nik', 'ktp_photo']);
        $this->assertDatabaseMissing('users', ['phone' => '6281234567890']);
    }

    /**
     * URL /users/{user} (dan turunannya) memakai `code`, bukan id database
     * — lihat CLAUDE.md "User". Id mentah tidak boleh ikut ter-resolve.
     */
    public function test_user_show_route_uses_code_not_raw_id(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->withRole('customer');

        $url = route('users.show', $customer);
        $this->assertStringContainsString($customer->code, $url);

        $this->actingAs($superadmin)->get($url)->assertOk();
        $this->actingAs($superadmin)->get('/users/'.$customer->id)->assertNotFound();
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

    /**
     * Regression — @forelse ($users as $user) di index.blade.php membuat
     * PHP mempertahankan $user (baris terakhir loop) di scope, walau
     * loop-nya sudah selesai. Modal "Tambah Pengguna" mem-@include form
     * yang sama SETELAH loop itu, jadi kalau 'user' tidak dipaksa null
     * eksplisit di pemanggil @include, field Nama modal ikut ter-prefill
     * dengan nama user terakhir di tabel alih-alih kosong.
     */
    public function test_create_modal_name_field_is_not_prefilled_from_listed_users(): void
    {
        $superadmin = $this->superadmin();
        $this->withRole('technician')->update(['name' => 'Nama Yang Tidak Boleh Bocor']);

        $response = $this->actingAs($superadmin)->get('/users');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/id="name"[\s\S]*?value=""/', $response->getContent());
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

        $response = $this->actingAs($superadmin)->put(route('users.update', $superadmin), [
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
        Storage::fake('local');

        $response = $this->actingAs($this->superadmin())->post('/users', $this->validCreatePayload([
            // Bulan 13 — tidak valid.
            'nik' => '3201011713000099',
        ]));

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseMissing('users', ['phone' => '6281234567890']);
    }

    /**
     * Aturan wajib NIK/foto KTP hanya berlaku saat create lewat form
     * /users (lihat CLAUDE.md "User") — akun yang sudah ada tanpa NIK
     * (mis. dibuat sebelum aturan ini, atau lewat jalur lain seperti quick-
     * create customer di modul Service) tetap bisa dilengkapi lewat form
     * edit seperti sebelumnya.
     */
    public function test_nik_can_be_set_on_first_update_when_not_provided_at_creation(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->withRole('customer');

        $response = $this->actingAs($superadmin)->put(route('users.update', $customer), [
            'name' => 'Budi Santoso Update',
            'phone' => (string) $customer->phone,
            'email' => $customer->email,
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
        Storage::fake('local');
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', $this->validCreatePayload([
            'nik' => self::NIK_MALE,
        ]));
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $response = $this->actingAs($superadmin)->put(route('users.update', $customer), [
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
        Storage::fake('local');
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', $this->validCreatePayload());
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        $response = $this->actingAs($superadmin)->delete(route('users.destroy', $customer));

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseMissing('users', ['id' => $customer->id]);
        $this->assertDatabaseMissing('user_details', ['id' => $customer->id]);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();
        User::factory()->create(['phone' => '6281234567890']);

        $response = $this->actingAs($superadmin)->post('/users', $this->validCreatePayload());

        $response->assertSessionHasErrors('phone');
    }

    /**
     * Regression — sebelum `UserRequest::messages()` ditambah, pesan yang
     * muncul cuma nama key mentah ("validation.unique") karena project ini
     * pakai APP_LOCALE=id tanpa file bahasa `lang/id/validation.php`.
     * Sekaligus membuktikan input yang erred tampil dengan border merah
     * (`$fieldClasses()` di users/_form.blade.php), bukan cuma teks di
     * bawahnya, supaya jelas kolom mana yang bermasalah.
     */
    public function test_duplicate_phone_shows_readable_message_and_highlights_the_field(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();
        User::factory()->create(['phone' => '6281234567890']);

        $this->actingAs($superadmin)->post('/users', $this->validCreatePayload());

        $response = $this->actingAs($superadmin)->get('/users');

        $response->assertOk();
        $response->assertDontSee('validation.unique');
        $response->assertSee('Nomor telepon ini sudah terdaftar untuk pengguna lain.');
        // Highlight visual sekarang lewat daisyUI: aria-invalid="true" di
        // <input id="phone"> memicu class .validator (border merah) lewat
        // CSS daisyUI, bukan kelas Tailwind manual lagi — lihat
        // users/_form.blade.php & CLAUDE.md "Catatan Stack".
        $this->assertMatchesRegularExpression('/id="phone"[\s\S]*?aria-invalid="true"/', $response->getContent());
    }

    public function test_duplicate_nik_is_rejected(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', $this->validCreatePayload([
            'nik' => self::NIK_MALE,
        ]));

        $response = $this->actingAs($superadmin)->post('/users', $this->validCreatePayload([
            'name' => 'Lain Orang',
            'phone' => '81299999999',
            'email' => 'lain.orang@example.com',
            'nik' => self::NIK_MALE,
        ]));

        $response->assertSessionHasErrors('nik');
    }

    public function test_customer_cannot_access_user_routes(): void
    {
        $customer = $this->withRole('customer');

        $this->actingAs($customer)->get('/users')->assertForbidden();
        $this->actingAs($customer)->post('/users', $this->validCreatePayload())->assertForbidden();
    }

    /**
     * Gate `/users` masih sengaja cuma untuk superadmin — technician/finance
     * belum dapat akses apa pun di iterasi ini (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_staff_roles_cannot_access_user_routes(): void
    {
        foreach (['technician', 'finance'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/users')->assertForbidden();
        }
    }

    public function test_ktp_photo_is_stored_privately_and_owner_can_view_it(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', $this->validCreatePayload());
        $customer = User::where('phone', '6281234567890')->firstOrFail();

        Storage::disk('local')->assertExists($customer->userDetails->ktp_photo);

        $response = $this->actingAs($customer)->get(route('secure.ktp', $customer));
        $response->assertOk();
    }

    public function test_other_non_admin_cannot_view_someone_elses_ktp_photo(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', $this->validCreatePayload());
        $owner = User::where('phone', '6281234567890')->firstOrFail();
        $stranger = $this->withRole('customer');

        $this->actingAs($stranger)->get(route('secure.ktp', $owner))->assertForbidden();
    }

    public function test_superadmin_can_view_any_ktp_photo(): void
    {
        Storage::fake('local');
        $superadmin = $this->superadmin();

        $this->actingAs($superadmin)->post('/users', $this->validCreatePayload());
        $owner = User::where('phone', '6281234567890')->firstOrFail();

        $this->actingAs($superadmin)->get(route('secure.ktp', $owner))->assertOk();
    }

    public function test_name_is_normalized_to_title_case(): void
    {
        Storage::fake('local');

        $this->actingAs($this->superadmin())->post('/users', $this->validCreatePayload([
            'name' => 'budi   SANTOSO',
        ]));

        $customer = User::where('phone', '6281234567890')->firstOrFail();
        $this->assertSame('Budi Santoso', $customer->name);
    }

    public function test_new_user_receives_whatsapp_welcome_notification(): void
    {
        Storage::fake('local');
        $gateway = $this->fakeGateway();

        $this->actingAs($this->superadmin())->post('/users', $this->validCreatePayload());

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

        $response = $this->actingAs($superadmin)->postJson(route('users.complete-kyc', $customer), [
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

        $response = $this->actingAs($superadmin)->postJson(route('users.complete-kyc', $customer), [
            'nik' => self::NIK_FEMALE,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('nik');
        $this->assertSame(self::NIK_MALE, $customer->fresh()->userDetails->nik);
    }

    /**
     * Role 'sales' dihapus total 2026-07-17 — technician & finance sekarang
     * ikut dapat users.complete-kyc, jadi satu-satunya role yang masih
     * relevan diuji "forbidden" di sini tinggal 'customer' (tidak pernah
     * dapat permission apa pun, lihat CLAUDE.md "Authorization / Role &
     * Permission").
     */
    public function test_complete_kyc_endpoint_forbidden_for_customer_role(): void
    {
        $actor = $this->withRole('customer');
        $customer = $this->withRole('customer');

        $this->actingAs($actor)->postJson(route('users.complete-kyc', $customer), [
            'nik' => self::NIK_MALE,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertForbidden();
    }

    /**
     * users.complete-kyc dibuka untuk seluruh role staff (technician &
     * finance, bukan lagi eksklusif role 'sales' yang sudah dihapus total)
     * — supaya alur registrasi pelanggan baru (modal "Lengkapi NIK & Foto
     * KTP" di form Service) tidak terblokir walau staff itu tidak punya
     * users.update penuh (lihat CLAUDE.md "Authorization / Role &
     * Permission").
     */
    public function test_complete_kyc_endpoint_allowed_for_staff_roles(): void
    {
        Storage::fake('local');

        // NIK harus beda per iterasi (unique constraint di user_details.nik
        // — keduanya benar-benar tersimpan, beda dari test forbidden di
        // atas yang gagal sebelum sempat menyimpan apa pun).
        foreach (['technician' => self::NIK_MALE, 'finance' => self::NIK_FEMALE] as $role => $nik) {
            $staff = $this->withRole($role);
            $customer = $this->withRole('customer');

            $response = $this->actingAs($staff)->postJson(route('users.complete-kyc', $customer), [
                'nik' => $nik,
                'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
            ]);

            $response->assertOk();
            $this->assertSame($nik, $customer->fresh()->userDetails->nik);
        }
    }
}
