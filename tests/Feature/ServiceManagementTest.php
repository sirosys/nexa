<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Package;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Subdistrict;
use App\Models\User;
use App\Models\UserDetail;
use App\Notifications\ServiceRegisteredNotification;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CapturingWhatsappGateway;
use Tests\Support\GeneratesValidNik;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use GeneratesValidNik, RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    /**
     * Customer "lengkap" (sudah punya NIK & foto KTP) — dipakai di hampir
     * semua test di sini karena gate wajib NIK+KTP (lihat CLAUDE.md
     * "Service") sekarang menolak pendaftaran Service baru untuk customer
     * yang belum lengkap. Pakai withRole('customer') langsung kalau
     * memang butuh customer yang SENGAJA belum lengkap.
     */
    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $nik = $this->validNik();
        $user->userDetails()->create(array_merge(
            ['nik' => $nik, 'ktp_photo' => 'ktp/fake-test-photo.jpg'],
            UserDetail::parseNik($nik)
        ));

        return $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    public function test_superadmin_can_create_service_with_auto_generated_code_and_pin(): void
    {
        $customer = $this->customer();
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true]);

        $response = $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 10',
            'residential_name' => 'Perumahan Griya Asri',
            'subdistrict_id' => $subdistrict->id,
            'rw' => '05',
            'rt' => '03',
            'coverage_id' => $coverage->id,
        ]);

        $response->assertRedirect(route('services.index'));

        $service = Service::where('address', 'Jl. Contoh No. 10')->firstOrFail();
        $this->assertNotNull($service->code);
        $this->assertStringStartsWith('SRV', $service->code);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $service->pin);
        $this->assertSame($customer->id, $service->user_id);
        $this->assertSame($package->id, $service->package_id);
        $this->assertSame(Service::STATUS_PENDING_PAYMENT, $service->status);
    }

    /**
     * services.create dibuka untuk role sales — alur registrasi pelanggan
     * baru end-to-end (lihat CLAUDE.md "Authorization / Role & Permission").
     */
    public function test_sales_role_can_create_service(): void
    {
        $customer = $this->customer();
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true]);

        $response = $this->actingAs($this->withRole('sales'))->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 10',
            'residential_name' => 'Perumahan Griya Asri',
            'subdistrict_id' => $subdistrict->id,
            'rw' => '05',
            'rt' => '03',
            'coverage_id' => $coverage->id,
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertDatabaseHas('services', ['address' => 'Jl. Contoh No. 10', 'user_id' => $customer->id]);
    }

    public function test_address_and_residential_name_are_normalized_to_title_case(): void
    {
        $customer = $this->customer();
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true]);

        $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'jl.   contoh No 99',
            'residential_name' => 'perumahan griya asri',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);

        $service = Service::where('user_id', $customer->id)->firstOrFail();
        $this->assertSame('Jl. Contoh No 99', $service->address);
        $this->assertSame('Perumahan Griya Asri', $service->residential_name);
    }

    /**
     * Pakai paket gratis (tanpa produk, grandtotal 0) supaya cuma
     * ServiceRegisteredNotification yang terkirim lewat WhatsApp di test
     * ini — InvoiceCreatedNotification sengaja tidak terkirim untuk Sale
     * gratis (lihat CLAUDE.md "Billing / Invoice (Xendit)"), jadi tidak
     * menimpa isi CapturingWhatsappGateway yang cuma menyimpan satu pesan
     * terakhir.
     */
    public function test_creating_service_sends_whatsapp_registration_notification(): void
    {
        $gateway = $this->fakeGateway();
        $customer = $this->customer();
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true]);

        $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 21',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);

        $service = Service::where('address', 'Jl. Contoh No. 21')->firstOrFail();

        $this->assertSame((string) $customer->phone, $gateway->phone);
        $this->assertStringContainsString($service->code, $gateway->message);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $customer->id,
            'type' => ServiceRegisteredNotification::class,
        ]);
    }

    /**
     * Sale (tagihan pendaftaran) untuk paket starter yang dipilih dibuat
     * otomatis saat service ditambahkan — staff tidak input manual ke
     * /sales terpisah untuk pendaftaran awal (lihat CLAUDE.md "Service").
     */
    public function test_creating_service_auto_generates_initial_sale_from_starter_package(): void
    {
        $customer = $this->customer();
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true]);
        $product = Product::factory()->create(['price' => 150000]);
        $package->products()->attach($product->id, ['quantity' => 2, 'price' => 150000]);

        $response = $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 20',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);

        $response->assertRedirect(route('services.index'));

        $service = Service::where('address', 'Jl. Contoh No. 20')->firstOrFail();
        $sale = Sale::where('service_id', $service->id)->firstOrFail();

        $this->assertStringStartsWith('SAL', $sale->code);
        $this->assertSame($package->id, $sale->package_id);
        $this->assertTrue($sale->is_starter);
        $this->assertEquals(300000, (float) $sale->total);
        $this->assertEquals(300000, (float) $sale->grandtotal);
        $this->assertDatabaseHas('sale_products', [
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_user_id_must_have_customer_role(): void
    {
        $staff = $this->withRole('technician');
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true]);

        $response = $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $staff->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 11',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);

        $response->assertSessionHasErrors('user_id');
    }

    public function test_package_must_be_selectable_at_registration(): void
    {
        $customer = $this->customer();
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => false]);

        $response = $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 13',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);

        $response->assertSessionHasErrors('package_id');
    }

    /**
     * Paket promo yang sudah melewati valid_until tidak boleh lagi dipilih
     * untuk pendaftaran baru — lihat CLAUDE.md "Product & Package".
     */
    public function test_expired_package_cannot_be_selected_at_registration(): void
    {
        $customer = $this->customer();
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true, 'valid_until' => now()->subDay()]);

        $response = $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 14',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);

        $response->assertSessionHasErrors('package_id');
    }

    /**
     * Paket unlimited (valid_until null) atau yang belum lewat tanggalnya
     * tetap bisa dipilih seperti biasa.
     */
    public function test_unlimited_or_still_valid_package_can_be_selected_at_registration(): void
    {
        $customer = $this->customer();
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true, 'valid_until' => now()->addMonth()]);

        $response = $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 15',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);

        $response->assertSessionDoesntHaveErrors('package_id');
    }

    public function test_expired_package_is_excluded_from_registration_dropdown(): void
    {
        $superadmin = $this->superadmin();
        $expired = Package::factory()->create(['is_starter' => true, 'valid_until' => now()->subDay(), 'name' => 'Paket Kadaluarsa Unik']);
        $available = Package::factory()->create(['is_starter' => true, 'valid_until' => null, 'name' => 'Paket Unlimited Unik']);

        $response = $this->actingAs($superadmin)->get('/services/create');

        $response->assertOk();
        $response->assertDontSee('Paket Kadaluarsa Unik');
        $response->assertSee('Paket Unlimited Unik');
    }

    public function test_subdistrict_coverage_and_package_must_exist(): void
    {
        $customer = $this->customer();

        $response = $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $customer->id,
            'package_id' => 999999,
            'address' => 'Jl. Contoh No. 12',
            'subdistrict_id' => 999999,
            'coverage_id' => 999999,
        ]);

        $response->assertSessionHasErrors(['subdistrict_id', 'coverage_id', 'package_id']);
    }

    public function test_superadmin_can_update_service_and_reset_pin(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create(['pin' => '111111']);

        $response = $this->actingAs($superadmin)->put("/services/{$service->id}", [
            'user_id' => $service->user_id,
            'package_id' => $service->package_id,
            'address' => 'Alamat Baru',
            'subdistrict_id' => $service->subdistrict_id,
            'coverage_id' => $service->coverage_id,
            'pin' => '222222',
        ]);

        $response->assertRedirect(route('services.index'));
        $service->refresh();
        $this->assertSame('Alamat Baru', $service->address);
        $this->assertSame('222222', $service->pin);
    }

    public function test_pin_must_be_six_digits_on_update(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();

        $response = $this->actingAs($superadmin)->put("/services/{$service->id}", [
            'user_id' => $service->user_id,
            'package_id' => $service->package_id,
            'address' => $service->address,
            'subdistrict_id' => $service->subdistrict_id,
            'coverage_id' => $service->coverage_id,
            'pin' => '12',
        ]);

        $response->assertSessionHasErrors('pin');
    }

    public function test_deleting_service_is_soft_delete(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();

        $response = $this->actingAs($superadmin)->delete("/services/{$service->id}");

        $response->assertRedirect(route('services.index'));
        $this->assertSoftDeleted('services', ['id' => $service->id]);
    }

    public function test_soft_deleted_service_hidden_from_listing(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create(['address' => 'Alamat Yang Dihapus']);
        $service->delete();

        $response = $this->actingAs($superadmin)->get('/services');

        $response->assertOk();
        $response->assertDontSee('Alamat Yang Dihapus');
    }

    public function test_restrict_on_delete_blocks_deleting_referenced_coverage(): void
    {
        $superadmin = $this->superadmin();
        $coverage = Coverage::factory()->create();
        Service::factory()->create(['coverage_id' => $coverage->id]);

        $this->actingAs($superadmin)->delete("/coverages/{$coverage->id}");

        $this->assertDatabaseHas('coverages', ['id' => $coverage->id]);
    }

    public function test_listing_shows_services(): void
    {
        $superadmin = $this->superadmin();
        Service::factory()->create(['address' => 'Jl. Kemang Raya No. 5']);

        $response = $this->actingAs($superadmin)->get('/services');

        $response->assertOk();
        $response->assertSee('Jl. Kemang Raya No. 5');
    }

    public function test_superadmin_can_view_service_detail(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create(['address' => 'Jl. Detail No. 1']);

        $response = $this->actingAs($superadmin)->get(route('services.show', $service));

        $response->assertOk();
        $response->assertSee('Jl. Detail No. 1');
        $response->assertSee($service->code);
    }

    public function test_create_and_edit_pages_render(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();

        $this->actingAs($superadmin)->get('/services/create')->assertOk();
        $this->actingAs($superadmin)->get("/services/{$service->id}/edit")->assertOk();
    }

    public function test_customer_search_endpoint_only_returns_customer_role(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->customer();
        $customer->update(['name' => 'Budi Santoso']);
        $staff = $this->withRole('technician');
        $staff->update(['name' => 'Budi Teknisi']);

        $response = $this->actingAs($superadmin)->getJson('/services/customers/search?q=Budi');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['name' => 'Budi Santoso']);
    }

    /**
     * Query kosong (klik pertama kali di kolom pencarian, belum mengetik
     * apa-apa) tetap mengembalikan daftar browse — bukan array kosong —
     * supaya picker pelanggan di form Service bisa dibuka lewat klik.
     */
    public function test_customer_search_endpoint_returns_browse_list_when_query_is_empty(): void
    {
        $superadmin = $this->superadmin();
        $this->customer();
        $this->customer();

        $response = $this->actingAs($superadmin)->getJson('/services/customers/search');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_non_superadmin_roles_cannot_access_service_routes(): void
    {
        foreach (['technician', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/services')->assertForbidden();
        }
    }

    /**
     * finance dan sales dapat services.view (finance: konteks tagihan;
     * sales: alur registrasi pelanggan) — lihat CLAUDE.md "Authorization /
     * Role & Permission".
     */
    public function test_finance_and_sales_roles_can_view_service_routes(): void
    {
        foreach (['finance', 'sales'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/services')->assertOk();
        }
    }

    /**
     * Search endpoint menyertakan has_nik/has_ktp_photo per baris — dipakai
     * frontend form Service untuk menggerbang "lengkapi NIK & foto KTP"
     * sebelum pelanggan bisa dipilih (lihat CLAUDE.md "Service").
     */
    public function test_customer_search_endpoint_reports_nik_and_ktp_completeness(): void
    {
        $superadmin = $this->superadmin();
        $complete = $this->customer();
        $incomplete = $this->withRole('customer');

        $response = $this->actingAs($superadmin)->getJson('/services/customers/search');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $complete->id, 'has_nik' => true, 'has_ktp_photo' => true]);
        $response->assertJsonFragment(['id' => $incomplete->id, 'has_nik' => false, 'has_ktp_photo' => false]);
    }

    /**
     * Gate wajib NIK & foto KTP sebelum registrasi Service baru — keputusan
     * bisnis yang diminta eksplisit, lihat CLAUDE.md "Service". Ditegakkan
     * di ServiceRequest sebagai pertahanan sisi server (UI form Service
     * sendiri menggerbang ini lewat modal sebelum submit).
     */
    public function test_service_registration_is_blocked_for_customer_missing_nik_or_ktp(): void
    {
        $incomplete = $this->withRole('customer');
        $subdistrict = Subdistrict::factory()->create();
        $coverage = Coverage::factory()->create();
        $package = Package::factory()->create(['is_starter' => true]);

        $response = $this->actingAs($this->superadmin())->post('/services', [
            'user_id' => $incomplete->id,
            'package_id' => $package->id,
            'address' => 'Jl. Contoh No. 30',
            'subdistrict_id' => $subdistrict->id,
            'coverage_id' => $coverage->id,
        ]);

        $response->assertSessionHasErrors('user_id');
        $this->assertDatabaseMissing('services', ['address' => 'Jl. Contoh No. 30']);
    }

    /**
     * Gate NIK/KTP cuma berlaku saat mendaftarkan Service BARU — Service
     * lama yang customer-nya belum lengkap (dibuat sebelum gate ini ada)
     * tetap harus bisa diedit, bukan terkunci total.
     */
    public function test_updating_existing_service_is_not_blocked_by_incomplete_customer_kyc(): void
    {
        $superadmin = $this->superadmin();
        $incomplete = $this->withRole('customer');
        $service = Service::factory()->create(['user_id' => $incomplete->id]);

        $response = $this->actingAs($superadmin)->put("/services/{$service->id}", [
            'user_id' => $service->user_id,
            'package_id' => $service->package_id,
            'address' => 'Alamat Baru Tanpa Data Lengkap',
            'subdistrict_id' => $service->subdistrict_id,
            'coverage_id' => $service->coverage_id,
            'pin' => '333333',
        ]);

        $response->assertRedirect(route('services.index'));
        $this->assertSame('Alamat Baru Tanpa Data Lengkap', $service->fresh()->address);
    }

    public function test_quick_create_customer_endpoint_creates_customer_and_sends_whatsapp_notification(): void
    {
        $gateway = $this->fakeGateway();
        $superadmin = $this->superadmin();

        $response = $this->actingAs($superadmin)->postJson('/services/customers', [
            'name' => 'pelanggan   baru',
            'phone' => '81234000111',
            'email' => 'pelanggan.baru@example.com',
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['name' => 'Pelanggan Baru', 'has_nik' => false, 'has_ktp_photo' => false]);

        $customer = User::where('phone', '6281234000111')->firstOrFail();
        $this->assertTrue($customer->hasRole('customer'));
        $this->assertSame('pelanggan.baru@example.com', $customer->email);
        $this->assertNotNull($customer->code);
        $this->assertSame((string) $customer->phone, $gateway->phone);
    }

    public function test_quick_create_customer_endpoint_rejects_duplicate_phone_or_email(): void
    {
        $superadmin = $this->superadmin();
        User::factory()->create(['phone' => '6281234000222', 'email' => 'sudah.ada@example.com']);

        $response = $this->actingAs($superadmin)->postJson('/services/customers', [
            'name' => 'Pelanggan Baru',
            'phone' => '81234000222',
            'email' => 'sudah.ada@example.com',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['phone', 'email']);
    }

    public function test_quick_create_customer_endpoint_forbidden_for_non_superadmin(): void
    {
        $staff = $this->withRole('technician');

        $this->actingAs($staff)->postJson('/services/customers', [
            'name' => 'Pelanggan Baru',
            'phone' => '81234000333',
            'email' => 'pelanggan.lain@example.com',
        ])->assertForbidden();
    }
}
