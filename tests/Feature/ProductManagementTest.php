<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
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

    public function test_superadmin_can_create_product_with_auto_generated_code(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/products', [
            'type' => 'perangkat',
            'name' => 'Modem ONT',
            'description' => 'Modem fiber standar.',
            'price' => 350000,
            'unit' => 'pcs',
        ]);

        $response->assertRedirect(route('products.index'));

        $product = Product::where('name', 'Modem ONT')->firstOrFail();
        $this->assertNotNull($product->code);
        $this->assertStringStartsWith('PRD', $product->code);
    }

    public function test_product_type_must_be_one_of_the_fixed_list(): void
    {
        $response = $this->actingAs($this->superadmin())->post('/products', [
            'type' => 'tidak-valid',
            'name' => 'Modem ONT',
            'price' => 350000,
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_superadmin_can_update_product(): void
    {
        $superadmin = $this->superadmin();
        $product = Product::factory()->create(['name' => 'Modem Lama']);

        $response = $this->actingAs($superadmin)->put("/products/{$product->id}", [
            'type' => $product->type,
            'name' => 'Modem Baru',
            'price' => 400000,
        ]);

        $response->assertRedirect(route('products.index'));
        $this->assertSame('Modem Baru', $product->fresh()->name);
    }

    public function test_superadmin_can_delete_product(): void
    {
        $superadmin = $this->superadmin();
        $product = Product::factory()->create();

        $response = $this->actingAs($superadmin)->delete("/products/{$product->id}");

        $response->assertRedirect(route('products.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_listing_shows_products(): void
    {
        $superadmin = $this->superadmin();
        Product::factory()->create(['name' => 'Router Wifi 6']);

        $response = $this->actingAs($superadmin)->get('/products');

        $response->assertOk();
        $response->assertSee('Router Wifi 6');
    }

    /**
     * Gate `/products` masih sengaja cuma untuk superadmin, konsisten dengan
     * gate `/users` (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_product_routes(): void
    {
        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/products')->assertForbidden();
        }
    }
}
