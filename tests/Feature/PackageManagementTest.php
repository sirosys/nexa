<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageManagementTest extends TestCase
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

    public function test_superadmin_can_create_package_with_bundled_products(): void
    {
        $superadmin = $this->superadmin();
        $plan = Plan::factory()->create();
        $modem = Product::factory()->create(['type' => 'perangkat', 'price' => 350000]);
        $installation = Product::factory()->create(['type' => 'jasa', 'price' => 150000]);

        $response = $this->actingAs($superadmin)->post('/packages', [
            'is_starter' => '1',
            'name' => 'Paket Gratis 3 Bulan',
            'description' => 'Promo pendaftaran baru.',
            'price' => 0,
            'plan_id' => $plan->id,
            'plan_price' => 0,
            'plan_qty' => 3,
            'products' => [
                ['product_id' => $modem->id, 'quantity' => 1, 'price' => 0],
                ['product_id' => $installation->id, 'quantity' => 1, 'price' => 0],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));

        $package = Package::where('name', 'Paket Gratis 3 Bulan')->firstOrFail();
        $this->assertNotNull($package->code);
        $this->assertStringStartsWith('PKG', $package->code);
        $this->assertTrue($package->is_starter);
        $this->assertSame($plan->id, $package->plan_id);
        $this->assertSame(0.0, (float) $package->plan_price);
        $this->assertSame(3, $package->plan_qty);
        $this->assertCount(2, $package->products);
        $this->assertDatabaseHas('package_product', [
            'package_id' => $package->id,
            'product_id' => $modem->id,
            'quantity' => 1,
        ]);
    }

    public function test_plan_id_is_required(): void
    {
        $product = Product::factory()->create(['type' => 'perangkat']);

        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Tanpa Plan',
            'price' => 100000,
            'plan_price' => 0,
            'plan_qty' => 1,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 100000],
            ],
        ]);

        $response->assertSessionHasErrors('plan_id');
    }

    public function test_plan_price_and_plan_qty_are_required(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Tanpa Plan Price/Qty',
            'price' => 100000,
            'plan_id' => $plan->id,
            'products' => [],
        ]);

        $response->assertSessionHasErrors(['plan_price', 'plan_qty', 'products']);
    }

    public function test_superadmin_can_update_package_plan_and_qty(): void
    {
        $superadmin = $this->superadmin();
        $plan = Plan::factory()->create();
        $package = Package::factory()->create();
        $originalProduct = Product::factory()->create(['type' => 'perangkat']);
        $package->products()->attach($originalProduct->id, ['quantity' => 1, 'price' => 100000]);

        $response = $this->actingAs($superadmin)->put("/packages/{$package->id}", [
            'name' => $package->name,
            'price' => $package->price,
            'plan_id' => $plan->id,
            'plan_price' => 130000,
            'plan_qty' => 12,
            'products' => [
                ['product_id' => $originalProduct->id, 'quantity' => 1, 'price' => 100000],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $package->refresh();
        $this->assertSame($plan->id, $package->plan_id);
        $this->assertSame(130000.0, (float) $package->plan_price);
        $this->assertSame(12, $package->plan_qty);
    }

    /**
     * `products[]` tetap wajib minimal 1 lewat form HTTP (rule di
     * PackageRequest tidak berubah) — paket tanpa produk lain sama sekali
     * (mis. "Semesteran" di seeder) cuma bisa dibuat lewat Eloquent
     * langsung, bukan lewat form staff.
     */
    public function test_package_products_are_still_required_via_form(): void
    {
        $plan = Plan::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Semesteran',
            'price' => 450000,
            'plan_id' => $plan->id,
            'plan_price' => 0,
            'plan_qty' => 6,
            'products' => [],
        ]);

        $response->assertSessionHasErrors('products');
    }

    public function test_duplicate_product_in_same_package_is_rejected(): void
    {
        $plan = Plan::factory()->create();
        $product = Product::factory()->create(['type' => 'perangkat']);

        $response = $this->actingAs($this->superadmin())->post('/packages', [
            'name' => 'Paket Duplikat',
            'price' => 100000,
            'plan_id' => $plan->id,
            'plan_price' => 0,
            'plan_qty' => 1,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 50000],
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 50000],
            ],
        ]);

        $response->assertSessionHasErrors('products');
    }

    public function test_superadmin_can_update_package_products(): void
    {
        $superadmin = $this->superadmin();
        $plan = Plan::factory()->create();
        $package = Package::factory()->create(['plan_id' => $plan->id, 'plan_price' => 0, 'plan_qty' => 1]);
        $originalProduct = Product::factory()->create(['type' => 'perangkat']);
        $package->products()->attach($originalProduct->id, ['quantity' => 1, 'price' => 100000]);

        $newProduct = Product::factory()->create(['type' => 'perangkat']);

        $response = $this->actingAs($superadmin)->put("/packages/{$package->id}", [
            'name' => $package->name,
            'price' => $package->price,
            'plan_id' => $plan->id,
            'plan_price' => 0,
            'plan_qty' => 1,
            'products' => [
                ['product_id' => $newProduct->id, 'quantity' => 2, 'price' => 75000],
            ],
        ]);

        $response->assertRedirect(route('packages.index'));
        $package->refresh();
        $this->assertCount(1, $package->products);
        $this->assertTrue($package->products->first()->is($newProduct));
    }

    public function test_superadmin_can_delete_package(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create();

        $response = $this->actingAs($superadmin)->delete("/packages/{$package->id}");

        $response->assertRedirect(route('packages.index'));
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }

    public function test_create_and_edit_pages_render(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create();

        $this->actingAs($superadmin)->get('/packages/create')->assertOk();
        $this->actingAs($superadmin)->get("/packages/{$package->id}/edit")->assertOk();
    }

    public function test_superadmin_can_view_package_detail_with_bundled_products(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create(['name' => 'Paket Detail']);
        $product = Product::factory()->create(['name' => 'Modem Detail']);
        $package->products()->attach($product->id, ['quantity' => 1, 'price' => 100000]);

        $response = $this->actingAs($superadmin)->get(route('packages.show', $package));

        $response->assertOk();
        $response->assertSee('Paket Detail');
        $response->assertSee('Modem Detail');
    }

    /**
     * Gate `/packages` masih sengaja cuma untuk superadmin, konsisten dengan
     * gate `/users` dan `/products` (lihat CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_package_routes(): void
    {
        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/packages')->assertForbidden();
        }
    }
}
