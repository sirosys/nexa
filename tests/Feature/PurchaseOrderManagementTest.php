<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseOrderManagementTest extends TestCase
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

    private function nonSerializedItem(): InventoryItem
    {
        $product = Product::factory()->create(['type' => 'perangkat']);

        return InventoryItem::create(['product_id' => $product->id, 'is_serialized' => false, 'quantity' => 0]);
    }

    private function serializedItem(): InventoryItem
    {
        $product = Product::factory()->create(['type' => 'perangkat']);

        return InventoryItem::create(['product_id' => $product->id, 'is_serialized' => true, 'quantity' => 0]);
    }

    public function test_superadmin_can_create_purchase_order_with_auto_generated_code_and_computed_total(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Elektronik']);
        $item = $this->nonSerializedItem();

        $response = $this->actingAs($this->superadmin())->post('/purchase-orders', [
            'vendor_id' => $vendor->id,
            'notes' => 'Pengadaan rutin.',
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 10, 'price' => 25000],
            ],
        ]);

        $purchaseOrder = PurchaseOrder::where('vendor_id', $vendor->id)->firstOrFail();
        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->assertNotNull($purchaseOrder->code);
        $this->assertStringStartsWith('PUR', $purchaseOrder->code);
        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $purchaseOrder->status);
        $this->assertSame(250000.0, (float) $purchaseOrder->total);
    }

    public function test_duplicate_inventory_item_in_submission_is_rejected(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Duplikat']);
        $item = $this->nonSerializedItem();

        $response = $this->actingAs($this->superadmin())->post('/purchase-orders', [
            'vendor_id' => $vendor->id,
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 5, 'price' => 10000],
                ['inventory_item_id' => $item->id, 'quantity' => 3, 'price' => 10000],
            ],
        ]);

        $response->assertSessionHasErrors('items');
    }

    public function test_purchase_order_cannot_be_updated_once_not_draft(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Update']);
        $item = $this->nonSerializedItem();
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);
        $purchaseOrder->inventoryItems()->attach($item->id, ['quantity' => 5, 'price' => 10000]);

        $response = $this->actingAs($this->superadmin())->put("/purchase-orders/{$purchaseOrder->id}", [
            'vendor_id' => $vendor->id,
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 8, 'price' => 12000],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(5, $purchaseOrder->fresh()->inventoryItems->first()->pivot->quantity);
    }

    public function test_superadmin_can_mark_draft_purchase_order_as_ordered(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Order']);
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_DRAFT]);

        $response = $this->actingAs($this->superadmin())->post("/purchase-orders/{$purchaseOrder->id}/order");

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $purchaseOrder->status);
        $this->assertNotNull($purchaseOrder->ordered_at);
    }

    public function test_marking_as_ordered_fails_for_non_draft_purchase_order(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Order Invalid']);
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);

        $response = $this->actingAs($this->superadmin())->post("/purchase-orders/{$purchaseOrder->id}/order");

        $response->assertSessionHas('error');
    }

    public function test_receiving_non_serialized_item_increments_stock_and_marks_received(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Terima']);
        $item = $this->nonSerializedItem();
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);
        $purchaseOrder->inventoryItems()->attach($item->id, ['quantity' => 15, 'price' => 20000]);

        $response = $this->actingAs($this->superadmin())->post("/purchase-orders/{$purchaseOrder->id}/receive");

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $purchaseOrder->status);
        $this->assertNotNull($purchaseOrder->received_at);
        $this->assertSame(15, $item->fresh()->quantity);

        $movement = InventoryMovement::where('inventory_item_id', $item->id)->firstOrFail();
        $this->assertSame($purchaseOrder->id, $movement->purchase_order_id);
        $this->assertSame(15, $movement->quantity);
    }

    public function test_receiving_serialized_item_requires_exact_serial_count(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Serial']);
        $item = $this->serializedItem();
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);
        $purchaseOrder->inventoryItems()->attach($item->id, ['quantity' => 2, 'price' => 500000]);

        // Cuma 1 serial dikirim padahal quantity 2 — ditolak.
        $response = $this->actingAs($this->superadmin())->post("/purchase-orders/{$purchaseOrder->id}/receive", [
            'serial_numbers' => [$item->id => 'SN-001'],
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(PurchaseOrder::STATUS_ORDERED, $purchaseOrder->fresh()->status);
        $this->assertSame(0, $item->fresh()->quantity);

        // Jumlah persis 2 — diterima.
        $response = $this->actingAs($this->superadmin())->post("/purchase-orders/{$purchaseOrder->id}/receive", [
            'serial_numbers' => [$item->id => "SN-001\nSN-002"],
        ]);

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $this->assertSame(PurchaseOrder::STATUS_RECEIVED, $purchaseOrder->fresh()->status);
        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertDatabaseHas('inventory_units', ['serial_number' => 'SN-001', 'inventory_item_id' => $item->id]);
        $this->assertDatabaseHas('inventory_units', ['serial_number' => 'SN-002', 'inventory_item_id' => $item->id]);
    }

    public function test_receiving_fails_for_non_ordered_purchase_order(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Draft']);
        $item = $this->nonSerializedItem();
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
        $purchaseOrder->inventoryItems()->attach($item->id, ['quantity' => 5, 'price' => 10000]);

        $response = $this->actingAs($this->superadmin())->post("/purchase-orders/{$purchaseOrder->id}/receive");

        $response->assertSessionHas('error');
    }

    public function test_superadmin_can_cancel_draft_or_ordered_purchase_order(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Batal']);
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_DRAFT]);

        $response = $this->actingAs($this->superadmin())->post("/purchase-orders/{$purchaseOrder->id}/cancel");

        $response->assertRedirect(route('purchase-orders.show', $purchaseOrder));
        $purchaseOrder->refresh();
        $this->assertSame(PurchaseOrder::STATUS_CANCELED, $purchaseOrder->status);
        $this->assertNotNull($purchaseOrder->canceled_at);
    }

    public function test_cancelling_fails_for_received_purchase_order(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Batal Invalid']);
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_RECEIVED, 'received_at' => now()]);

        $response = $this->actingAs($this->superadmin())->post("/purchase-orders/{$purchaseOrder->id}/cancel");

        $response->assertSessionHas('error');
    }

    public function test_superadmin_can_delete_purchase_order(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Hapus']);
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_DRAFT]);

        $response = $this->actingAs($this->superadmin())->delete("/purchase-orders/{$purchaseOrder->id}");

        $response->assertRedirect(route('purchase-orders.index'));
        $this->assertSoftDeleted('purchase_orders', ['id' => $purchaseOrder->id]);
    }

    public function test_listing_shows_purchase_orders(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Listing']);
        PurchaseOrder::create(['code' => 'PUR000099', 'vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_DRAFT]);

        $response = $this->actingAs($this->superadmin())->get('/purchase-orders');

        $response->assertOk();
        $response->assertSee('PUR000099');
    }

    public function test_superadmin_can_view_purchase_order_detail(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Detail PO']);
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_DRAFT]);

        $response = $this->actingAs($this->superadmin())->get(route('purchase-orders.show', $purchaseOrder));

        $response->assertOk();
        $response->assertSee($purchaseOrder->code);
    }

    public function test_create_page_renders(): void
    {
        $this->actingAs($this->superadmin())->get('/purchase-orders/create')->assertOk();
    }

    public function test_edit_page_renders_with_mixed_serialized_and_non_serialized_items(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Edit Render']);
        $nonSerialized = $this->nonSerializedItem();
        $serialized = $this->serializedItem();
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_DRAFT]);
        $purchaseOrder->inventoryItems()->attach([
            $nonSerialized->id => ['quantity' => 3, 'price' => 15000],
            $serialized->id => ['quantity' => 2, 'price' => 300000],
        ]);

        $this->actingAs($this->superadmin())->get("/purchase-orders/{$purchaseOrder->id}/edit")->assertOk();
    }

    public function test_show_page_renders_receive_form_for_ordered_purchase_order_with_mixed_items(): void
    {
        $vendor = Vendor::create(['name' => 'Vendor Show Render']);
        $nonSerialized = $this->nonSerializedItem();
        $serialized = $this->serializedItem();
        $purchaseOrder = PurchaseOrder::create(['vendor_id' => $vendor->id, 'status' => PurchaseOrder::STATUS_ORDERED, 'ordered_at' => now()]);
        $purchaseOrder->inventoryItems()->attach([
            $nonSerialized->id => ['quantity' => 3, 'price' => 15000],
            $serialized->id => ['quantity' => 2, 'price' => 300000],
        ]);

        $response = $this->actingAs($this->superadmin())->get(route('purchase-orders.show', $purchaseOrder));

        $response->assertOk();
        $response->assertSee('Terima Barang');
    }

    /**
     * Gate `/purchase-orders` superadmin-only untuk iterasi ini — konsisten
     * pola gate konservatif modul katalog/master data lain (lihat CLAUDE.md
     * "Vendor & Supplier").
     */
    public function test_non_superadmin_roles_cannot_access_purchase_order_routes(): void
    {
        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $staff = $this->withRole($role);

            $this->actingAs($staff)->get('/purchase-orders')->assertForbidden();
        }
    }
}
