<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Package;
use App\Models\Service;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
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
     * Gate `/services` masih sengaja cuma untuk superadmin, konsisten dengan
     * gate `/users` dan `/products` (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_service_routes(): void
    {
        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/services')->assertForbidden();
        }
    }
}
