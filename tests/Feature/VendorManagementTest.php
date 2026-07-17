<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorManagementTest extends TestCase
{
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

    public function test_superadmin_can_create_vendor_with_auto_generated_code(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/vendors', [
            'name' => 'PT Maju Jaya',
            'contact_person' => 'Budi',
            'phone' => '021-5551234',
            'email' => 'budi@majujaya.test',
            'address' => 'Jl. Industri No. 1',
            'notes' => 'Distributor perangkat jaringan.',
        ]);

        $vendor = Vendor::where('name', 'PT Maju Jaya')->firstOrFail();
        $response->assertRedirect(route('vendors.index'));
        $this->assertNotNull($vendor->code);
        $this->assertStringStartsWith('VEN', $vendor->code);
        $this->assertSame('Budi', $vendor->contact_person);
    }

    public function test_name_is_required(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/vendors', [
            'name' => '',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_superadmin_can_update_vendor(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Lama']);

        $response = $this->actingAs($this->superadmin())->put("/vendors/{$vendor->id}", [
            'name' => 'Vendor Baru',
        ]);

        $response->assertRedirect(route('vendors.index'));
        $this->assertSame('Vendor Baru', $vendor->fresh()->name);
    }

    public function test_superadmin_can_delete_vendor(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Dihapus']);

        $response = $this->actingAs($this->superadmin())->delete("/vendors/{$vendor->id}");

        $response->assertRedirect(route('vendors.index'));
        $this->assertDatabaseMissing('vendors', ['id' => $vendor->id]);
    }

    public function test_listing_shows_vendors(): void
    {
        Vendor::create(['name' => 'Vendor Terlihat']);

        $response = $this->actingAs($this->superadmin())->get('/vendors');

        $response->assertOk();
        $response->assertSee('Vendor Terlihat');
    }

    public function test_superadmin_can_view_vendor_detail(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Detail']);

        $response = $this->actingAs($this->superadmin())->get(route('vendors.show', $vendor));

        $response->assertOk();
        $response->assertSee('Vendor Detail');
    }

    public function test_create_and_edit_pages_render(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Render']);

        $this->actingAs($this->superadmin())->get('/vendors/create')->assertOk();
        $this->actingAs($this->superadmin())->get("/vendors/{$vendor->id}/edit")->assertOk();
    }

    /**
     * Gate `/vendors` superadmin-only untuk iterasi ini — konsisten pola
     * gate konservatif modul katalog/master data lain (lihat CLAUDE.md
     * "Vendor & Supplier").
     */
    public function test_non_superadmin_roles_cannot_access_vendor_routes(): void
    {
        foreach (['technician', 'finance', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/vendors')->assertForbidden();
        }
    }
}
