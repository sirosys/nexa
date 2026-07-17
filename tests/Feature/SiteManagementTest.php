<?php

namespace Tests\Feature;

use App\Models\Coverage;
use App\Models\Site;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteManagementTest extends TestCase
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

    public function test_superadmin_can_create_site_with_auto_generated_code(): void
    {
        $subdistrict = Subdistrict::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/sites', [
            'name' => 'Site Menteng',
            'subdistrict_id' => $subdistrict->id,
            'serial' => 'SN-12345678',
            'model' => 'MikroTik CCR2004',
            'location' => 'Jl. Contoh No. 1',
            'token' => 'rahasia-token',
        ]);

        $response->assertRedirect(route('sites.index'));

        $site = Site::where('name', 'Site Menteng')->firstOrFail();
        $this->assertNotNull($site->code);
        $this->assertStringStartsWith('SIT', $site->code);
        $this->assertSame($subdistrict->id, $site->subdistrict_id);
        $this->assertSame('rahasia-token', $site->token);
    }

    public function test_superadmin_can_set_mikrotik_rest_api_fields(): void
    {
        $subdistrict = Subdistrict::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/sites', [
            'name' => 'Site Wireguard',
            'subdistrict_id' => $subdistrict->id,
            'host' => '172.16.0.1',
            'api_port' => 443,
            'api_username' => 'api',
        ]);

        $response->assertRedirect(route('sites.index'));

        $site = Site::where('name', 'Site Wireguard')->firstOrFail();
        $this->assertSame('172.16.0.1', $site->host);
        $this->assertSame(443, $site->api_port);
        $this->assertSame('api', $site->api_username);
    }

    public function test_site_token_is_hidden_from_serialization(): void
    {
        $site = Site::factory()->create(['token' => 'rahasia-token']);

        $this->assertArrayNotHasKey('token', $site->toArray());
    }

    public function test_subdistrict_id_must_exist(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/sites', [
            'name' => 'Site Tidak Valid',
            'subdistrict_id' => 999999,
        ]);

        $response->assertSessionHasErrors('subdistrict_id');
    }

    public function test_superadmin_can_update_site_without_clearing_token_when_left_blank(): void
    {
        $superadmin = $this->superadmin();
        $site = Site::factory()->create(['name' => 'Site Lama', 'token' => 'token-lama']);

        $response = $this->actingAs($superadmin)->put("/sites/{$site->id}", [
            'name' => 'Site Baru',
            'subdistrict_id' => $site->subdistrict_id,
        ]);

        $response->assertRedirect(route('sites.index'));
        $site->refresh();
        $this->assertSame('Site Baru', $site->name);
        $this->assertSame('token-lama', $site->token);
    }

    public function test_superadmin_can_delete_site(): void
    {
        $superadmin = $this->superadmin();
        $site = Site::factory()->create();

        $response = $this->actingAs($superadmin)->delete("/sites/{$site->id}");

        $response->assertRedirect(route('sites.index'));
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
    }

    public function test_deleting_site_with_coverage_is_restricted(): void
    {
        $superadmin = $this->superadmin();
        $site = Site::factory()->create();
        Coverage::factory()->create(['site_id' => $site->id]);

        $this->actingAs($superadmin)->delete("/sites/{$site->id}");

        $this->assertDatabaseHas('sites', ['id' => $site->id]);
    }

    public function test_listing_shows_sites(): void
    {
        $superadmin = $this->superadmin();
        Site::factory()->create(['name' => 'Site Kemang']);

        $response = $this->actingAs($superadmin)->get('/sites');

        $response->assertOk();
        $response->assertSee('Site Kemang');
    }

    public function test_superadmin_can_view_site_detail_with_coverages(): void
    {
        $superadmin = $this->superadmin();
        $site = Site::factory()->create(['name' => 'Site Detail']);
        Coverage::factory()->create(['site_id' => $site->id, 'name' => 'Coverage Detail']);

        $response = $this->actingAs($superadmin)->get(route('sites.show', $site));

        $response->assertOk();
        $response->assertSee('Site Detail');
        $response->assertSee('Coverage Detail');
    }

    public function test_create_and_edit_pages_render(): void
    {
        $superadmin = $this->superadmin();
        $site = Site::factory()->create();

        $this->actingAs($superadmin)->get('/sites/create')->assertOk();
        $this->actingAs($superadmin)->get("/sites/{$site->id}/edit")->assertOk();
    }

    public function test_subdistrict_search_endpoint_returns_matches(): void
    {
        $superadmin = $this->superadmin();
        Subdistrict::factory()->create(['name' => 'Kebayoran Baru']);
        Subdistrict::factory()->create(['name' => 'Kebayoran Lama']);
        Subdistrict::factory()->create(['name' => 'Menteng']);

        $response = $this->actingAs($superadmin)->getJson('/subdistricts/search?q=Kebayoran');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    /**
     * Gate `/sites` masih sengaja cuma untuk superadmin, konsisten dengan
     * gate `/users` dan `/products` (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_site_routes(): void
    {
        foreach (['technician', 'finance', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/sites')->assertForbidden();
        }
    }
}
