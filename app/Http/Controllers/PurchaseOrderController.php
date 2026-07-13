<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseOrderReceiveRequest;
use App\Http\Requests\PurchaseOrderRequest;
use App\Models\InventoryItem;
use App\Models\PurchaseOrder;
use App\Models\Vendor;
use App\Services\PurchaseOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class PurchaseOrderController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $purchaseOrderService)
    {
        $this->authorizeResource(PurchaseOrder::class, 'purchase_order');
    }

    public function index(Request $request): View
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with('vendor')
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $q = $request->string('q')->trim()->value();
                $query->where(function ($query) use ($q) {
                    $query->where('code', 'like', "%{$q}%")
                        ->orWhereHas('vendor', fn ($query) => $query->where('name', 'like', "%{$q}%"));
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchase-orders.index', ['purchaseOrders' => $purchaseOrders, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('purchase-orders.create', [
            'vendors' => Vendor::orderBy('name')->get(),
            'inventoryItems' => InventoryItem::with('product')->get(),
        ]);
    }

    public function store(PurchaseOrderRequest $request): RedirectResponse
    {
        $purchaseOrder = $this->purchaseOrderService->create($request->validated());

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Purchase Order berhasil dibuat.');
    }

    public function show(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load(['vendor', 'inventoryItems.product']);

        return view('purchase-orders.show', ['purchaseOrder' => $purchaseOrder]);
    }

    public function edit(PurchaseOrder $purchaseOrder): View
    {
        $purchaseOrder->load('inventoryItems');

        return view('purchase-orders.edit', [
            'purchaseOrder' => $purchaseOrder,
            'vendors' => Vendor::orderBy('name')->get(),
            'inventoryItems' => InventoryItem::with('product')->get(),
        ]);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        try {
            $this->purchaseOrderService->update($purchaseOrder, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Purchase Order berhasil diperbarui.');
    }

    public function destroy(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->purchaseOrderService->delete($purchaseOrder);

        return redirect()->route('purchase-orders.index')->with('status', 'Purchase Order berhasil dihapus.');
    }

    public function order(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('order', $purchaseOrder);

        try {
            $this->purchaseOrderService->markOrdered($purchaseOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Purchase Order berhasil ditandai dipesan.');
    }

    public function receive(PurchaseOrderReceiveRequest $request, PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('receive', $purchaseOrder);

        try {
            $this->purchaseOrderService->receive($purchaseOrder, $request->validated('serial_numbers', []));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Barang berhasil diterima dan stok telah diperbarui.');
    }

    public function cancel(PurchaseOrder $purchaseOrder): RedirectResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        try {
            $this->purchaseOrderService->cancel($purchaseOrder);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('status', 'Purchase Order berhasil dibatalkan.');
    }
}
