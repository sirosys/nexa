<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryAdjustmentRequest;
use App\Http\Requests\InventoryItemRequest;
use App\Http\Requests\InventoryStockInRequest;
use App\Models\InventoryItem;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class InventoryItemController extends Controller
{
    public function __construct(private readonly InventoryService $inventoryService)
    {
        // Nama parameter 'item' (bukan default 'inventory_item') — harus
        // cocok dengan ->parameters(['inventory-items' => 'item']) di
        // routes/web.php, kalau tidak authorizeResource() gagal
        // menemukan model yang di-bind untuk show/update/destroy.
        $this->authorizeResource(InventoryItem::class, 'item');
    }

    public function index(): View
    {
        $items = InventoryItem::query()
            ->with('product')
            ->latest('id')
            ->paginate(15);

        return view('inventory-items.index', ['items' => $items]);
    }

    public function create(): View
    {
        return view('inventory-items.create', [
            'products' => Product::where('type', 'perangkat')
                ->whereDoesntHave('inventoryItem')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(InventoryItemRequest $request): RedirectResponse
    {
        $item = $this->inventoryService->createItem($request->validated());

        return redirect()->route('inventory-items.show', $item)->with('status', 'Item inventaris berhasil ditambahkan.');
    }

    public function show(InventoryItem $item): View
    {
        $item->load(['product', 'units' => fn ($query) => $query->latest('id'), 'movements' => fn ($query) => $query->latest('id')->limit(20)->with(['unit', 'service'])]);

        return view('inventory-items.show', ['item' => $item]);
    }

    public function destroy(InventoryItem $item): RedirectResponse
    {
        $this->inventoryService->delete($item);

        return redirect()->route('inventory-items.index')->with('status', 'Item inventaris berhasil dihapus.');
    }

    public function stockIn(InventoryStockInRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $this->inventoryService->stockIn($item, $request->validated());

        return redirect()->route('inventory-items.show', $item)->with('status', 'Stok berhasil ditambahkan.');
    }

    public function adjust(InventoryAdjustmentRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        try {
            $this->inventoryService->adjustStock($item, (int) $request->validated('delta'), $request->validated('notes'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('inventory-items.show', $item)->with('status', 'Stok berhasil disesuaikan.');
    }
}
