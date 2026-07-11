<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleManagementTest extends TestCase
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

    public function test_superadmin_can_create_sale_with_auto_generated_code(): void
    {
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/sales', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 100000, 'discount' => 0],
            ],
        ]);

        $response->assertRedirect(route('sales.index'));

        $sale = Sale::where('service_id', $service->id)->firstOrFail();
        $this->assertNotNull($sale->code);
        $this->assertStringStartsWith('SAL', $sale->code);
        $this->assertMatchesRegularExpression('/^SAL\d{6}$/', $sale->code);
    }

    public function test_totals_calculated_correctly_from_line_items_including_per_line_discount(): void
    {
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $this->actingAs($this->superadmin())->post('/sales', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'tax' => 5000,
            'admin_fee' => 2500,
            'products' => [
                ['product_id' => $productA->id, 'quantity' => 2, 'price' => 100000, 'discount' => 10000],
                ['product_id' => $productB->id, 'quantity' => 1, 'price' => 50000, 'discount' => 0],
            ],
        ]);

        $sale = Sale::where('service_id', $service->id)->firstOrFail();

        // total = (2*100000) + (1*50000) = 250000, sebelum diskon apa pun.
        $this->assertEquals(250000, (float) $sale->total);
        // discount = SUM(sale_products.discount) = 10000.
        $this->assertEquals(10000, (float) $sale->discount);
        // subtotal = total - discount = 240000.
        $this->assertEquals(240000, (float) $sale->subtotal);
        // grandtotal = subtotal + tax + admin_fee = 240000+5000+2500.
        $this->assertEquals(247500, (float) $sale->grandtotal);
    }

    public function test_is_starter_snapshotted_from_selected_package(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $starterPackage = Package::factory()->create(['is_starter' => true]);
        $nonStarterPackage = Package::factory()->create(['is_starter' => false]);
        $product = Product::factory()->create();

        $this->actingAs($superadmin)->post('/sales', [
            'service_id' => $service->id,
            'package_id' => $starterPackage->id,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $sale = Sale::where('service_id', $service->id)->firstOrFail();
        $this->assertTrue($sale->is_starter);

        $this->actingAs($superadmin)->put("/sales/{$sale->id}", [
            'service_id' => $service->id,
            'package_id' => $nonStarterPackage->id,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $sale->refresh();
        $this->assertFalse($sale->is_starter);
    }

    public function test_guard_duplicate_product_in_submission(): void
    {
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/sales', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 1, 'price' => 10000],
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 10000],
            ],
        ]);

        $response->assertSessionHasErrors('products');
    }

    /**
     * Rule::exists() Laravel query langsung ke tabel, tidak menghormati
     * global scope SoftDeletes milik Service — SaleRequest wajib eksplisit
     * whereNull('deleted_at') supaya celah ini tertutup.
     */
    public function test_service_id_must_not_reference_soft_deleted_service(): void
    {
        $service = Service::factory()->create();
        $service->delete();
        $package = Package::factory()->create();
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/sales', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $response->assertSessionHasErrors('service_id');
    }

    public function test_service_id_and_package_id_must_exist(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->superadmin())->post('/sales', [
            'service_id' => 999999,
            'package_id' => 999999,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $response->assertSessionHasErrors(['service_id', 'package_id']);
    }

    /**
     * total/subtotal/discount/grandtotal sengaja tidak ada di
     * SaleRequest::rules(), jadi walau dikirim dari client, angka itu tidak
     * pernah lolos ke $data tervalidasi — SaleService selalu menghitung
     * ulang penuh dari products[].
     */
    public function test_totals_cannot_be_overridden_from_request(): void
    {
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($this->superadmin())->post('/sales', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'total' => 999999999,
            'subtotal' => 999999999,
            'discount' => 999999999,
            'grandtotal' => 999999999,
            'products' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 10000]],
        ]);

        $sale = Sale::where('service_id', $service->id)->firstOrFail();
        $this->assertEquals(10000, (float) $sale->total);
        $this->assertEquals(10000, (float) $sale->grandtotal);
    }

    public function test_superadmin_can_update_sale_and_recalculate_totals_when_line_items_change(): void
    {
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();

        $sale = Sale::factory()->create(['service_id' => $service->id, 'package_id' => $package->id]);
        app(SaleService::class)->syncProductsAndRecalculate($sale, [
            ['product_id' => $productA->id, 'quantity' => 1, 'price' => 10000],
        ]);

        $this->actingAs($this->superadmin())->put("/sales/{$sale->id}", [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $productB->id, 'quantity' => 3, 'price' => 20000],
            ],
        ]);

        $sale->refresh();
        $this->assertEquals(60000, (float) $sale->total);
        $this->assertCount(1, $sale->products);
        $this->assertSame($productB->id, $sale->products->first()->id);
    }

    public function test_deleting_sale_is_soft_delete(): void
    {
        $sale = Sale::factory()->create();

        $response = $this->actingAs($this->superadmin())->delete("/sales/{$sale->id}");

        $response->assertRedirect(route('sales.index'));
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
    }

    public function test_soft_deleted_sale_hidden_from_listing(): void
    {
        $sale = Sale::factory()->create();
        $sale->delete();

        $response = $this->actingAs($this->superadmin())->get('/sales');

        $response->assertOk();
        $response->assertDontSee($sale->code);
    }

    public function test_restrict_on_delete_blocks_deleting_referenced_package(): void
    {
        $superadmin = $this->superadmin();
        $package = Package::factory()->create();
        Sale::factory()->create(['package_id' => $package->id]);

        $this->actingAs($superadmin)->delete("/packages/{$package->id}");

        $this->assertDatabaseHas('packages', ['id' => $package->id]);
    }

    /**
     * Service::delete() normal itu soft (tidak menyentuh FK sama sekali) —
     * berbeda dari test restrict-on-delete lain yang lewat HTTP, di sini
     * forceDelete() dipanggil langsung di level model supaya benar-benar
     * memicu constraint restrictOnDelete pada sales.service_id.
     */
    public function test_restrict_on_delete_blocks_force_deleting_referenced_service(): void
    {
        $service = Service::factory()->create();
        Sale::factory()->create(['service_id' => $service->id]);

        $this->expectException(QueryException::class);

        $service->forceDelete();
    }

    public function test_search_services_endpoint_returns_browse_list_when_query_is_empty(): void
    {
        $superadmin = $this->superadmin();
        Service::factory()->count(2)->create();

        $response = $this->actingAs($superadmin)->getJson('/sales/services/search');

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_search_services_endpoint_matches_code_address_customer_name_or_phone(): void
    {
        $superadmin = $this->superadmin();
        $customer = $this->customer();
        $customer->update(['name' => 'Budi Santoso']);
        $service = Service::factory()->create(['user_id' => $customer->id, 'address' => 'Jl. Mangga No. 1']);
        Service::factory()->create(['address' => 'Jl. Lain No. 2']);

        $response = $this->actingAs($superadmin)->getJson('/sales/services/search?q=Budi');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['id' => $service->id]);
    }

    public function test_search_services_endpoint_excludes_soft_deleted_services(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $service->delete();

        $response = $this->actingAs($superadmin)->getJson('/sales/services/search');

        $response->assertOk();
        $response->assertJsonCount(0);
    }

    public function test_listing_shows_sales_and_searches_by_code_or_service_code(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $sale = Sale::factory()->create(['service_id' => $service->id]);

        $this->actingAs($superadmin)->get('/sales?q='.$sale->code)->assertOk()->assertSee($sale->code);
        $this->actingAs($superadmin)->get('/sales?q='.$service->code)->assertOk()->assertSee($sale->code);
    }

    public function test_superadmin_can_view_sale_detail_with_products(): void
    {
        $superadmin = $this->superadmin();
        $service = Service::factory()->create();
        $package = Package::factory()->create();
        $product = Product::factory()->create(['name' => 'Modem Detail']);

        $this->actingAs($superadmin)->post('/sales', [
            'service_id' => $service->id,
            'package_id' => $package->id,
            'products' => [
                ['product_id' => $product->id, 'quantity' => 2, 'price' => 100000, 'discount' => 0],
            ],
        ]);

        $sale = Sale::where('service_id', $service->id)->firstOrFail();

        $response = $this->actingAs($superadmin)->get(route('sales.show', $sale));

        $response->assertOk();
        $response->assertSee($sale->code);
        $response->assertSee('Modem Detail');
    }

    public function test_create_and_edit_pages_render(): void
    {
        $superadmin = $this->superadmin();
        $sale = Sale::factory()->create();

        $this->actingAs($superadmin)->get('/sales/create')->assertOk();
        $this->actingAs($superadmin)->get("/sales/{$sale->id}/edit")->assertOk();
    }

    /**
     * Gate `/sales` konsisten dengan modul lain — cuma superadmin (lihat
     * CLAUDE.md "Authorization").
     */
    public function test_non_superadmin_roles_cannot_access_sale_routes(): void
    {
        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/sales')->assertForbidden();
        }
    }
}
