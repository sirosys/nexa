<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PurchaseOrderService
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $purchaseOrder = PurchaseOrder::create([
                'vendor_id' => $data['vendor_id'],
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $purchaseOrder->update([
                'code' => 'PUR'.str_pad((string) $purchaseOrder->id, 6, '0', STR_PAD_LEFT),
            ]);

            $this->syncItemsAndRecalculate($purchaseOrder, $data['items']);

            return $purchaseOrder;
        });
    }

    /**
     * Line item cuma boleh diubah selagi PO masih draf — begitu sudah
     * ordered/received/canceled, isi barang yang dipesan dianggap final
     * (konsisten pola "retry tidak didukung" di modul lain).
     */
    public function update(PurchaseOrder $purchaseOrder, array $data): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new RuntimeException('Purchase Order yang sudah diproses (bukan draf) tidak bisa diubah lagi.');
        }

        return DB::transaction(function () use ($purchaseOrder, $data) {
            $purchaseOrder->update([
                'vendor_id' => $data['vendor_id'],
                'notes' => $data['notes'] ?? null,
                'updated_by' => Auth::id(),
            ]);

            $this->syncItemsAndRecalculate($purchaseOrder, $data['items']);

            return $purchaseOrder;
        });
    }

    public function delete(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->delete();
    }

    /**
     * Sync line item ke pivot purchase_order_products, lalu hitung ulang
     * total dari baris yang baru disimpan — pola sama
     * SaleService::syncProductsAndRecalculate(), tidak pernah dipercaya
     * dari request client.
     *
     * @param  array<int, array{inventory_item_id: int, quantity: int, price: float|string}>  $items
     */
    public function syncItemsAndRecalculate(PurchaseOrder $purchaseOrder, array $items): void
    {
        $pivotData = collect($items)->mapWithKeys(fn (array $row) => [
            $row['inventory_item_id'] => [
                'quantity' => $row['quantity'],
                'price' => $row['price'],
            ],
        ])->all();

        $purchaseOrder->inventoryItems()->sync($pivotData);

        $total = collect($items)->sum(fn (array $row) => $row['price'] * $row['quantity']);

        $purchaseOrder->update(['total' => $total]);
    }

    public function markOrdered(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw new RuntimeException('Cuma Purchase Order berstatus draf yang bisa ditandai dipesan.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_ORDERED,
            'ordered_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->auditLogService->record('purchase_order.ordered', $purchaseOrder, "Purchase Order {$purchaseOrder->code} ditandai dipesan.");

        return $purchaseOrder;
    }

    public function cancel(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if (! in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true)) {
            throw new RuntimeException('Purchase Order yang sudah diterima/dibatalkan tidak bisa dibatalkan lagi.');
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_CANCELED,
            'canceled_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        $this->auditLogService->record('purchase_order.canceled', $purchaseOrder, "Purchase Order {$purchaseOrder->code} dibatalkan.");

        return $purchaseOrder;
    }

    /**
     * Terima barang — untuk tiap baris item yang is_serialized, staff wajib
     * mengisi serial number sejumlah persis quantity yang dipesan (tidak
     * ada penerimaan sebagian/partial di iterasi ini, lihat CLAUDE.md
     * "Vendor & Supplier"). Item non-serialized langsung distok sejumlah
     * quantity yang dipesan tanpa input tambahan.
     *
     * @param  array<int, string>  $serialNumbersByItem  keyed by inventory_item_id
     */
    public function receive(PurchaseOrder $purchaseOrder, array $serialNumbersByItem): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_ORDERED) {
            throw new RuntimeException('Cuma Purchase Order berstatus dipesan yang bisa diterima.');
        }

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $serialNumbersByItem) {
            $purchaseOrder->load('inventoryItems');

            foreach ($purchaseOrder->inventoryItems as $item) {
                /** @var InventoryItem $item */
                $quantity = (int) $item->pivot->quantity;

                if ($item->is_serialized) {
                    $raw = $serialNumbersByItem[$item->id] ?? '';
                    $serials = collect(preg_split('/\r\n|\r|\n/', $raw))
                        ->map(fn (string $line) => trim($line))
                        ->filter(fn (string $line) => $line !== '')
                        ->unique()
                        ->values();

                    if ($serials->count() !== $quantity) {
                        throw new RuntimeException("Jumlah serial number untuk \"{$item->product?->name}\" harus persis {$quantity} (saat ini {$serials->count()}).");
                    }

                    $this->inventoryService->stockIn($item, [
                        'serial_numbers' => $serials->implode("\n"),
                        'notes' => "Penerimaan {$purchaseOrder->code}",
                        'purchase_order_id' => $purchaseOrder->id,
                    ]);

                    continue;
                }

                $this->inventoryService->stockIn($item, [
                    'quantity' => $quantity,
                    'notes' => "Penerimaan {$purchaseOrder->code}",
                    'purchase_order_id' => $purchaseOrder->id,
                ]);
            }

            $purchaseOrder->update([
                'status' => PurchaseOrder::STATUS_RECEIVED,
                'received_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            return $purchaseOrder;
        });

        $this->auditLogService->record('purchase_order.received', $purchaseOrder, "Purchase Order {$purchaseOrder->code} diterima, stok Inventaris diperbarui.");

        return $purchaseOrder;
    }
}
