<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceOrderRequest;
use App\Models\Package;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\ReceiptService;
use App\Services\ServiceOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceOrderController extends Controller
{
    public function __construct(
        private readonly ServiceOrderService $serviceOrderService,
        private readonly ReceiptService $receiptService,
    ) {
        $this->authorizeResource(ServiceOrder::class, 'service_order');
    }

    public function index(Request $request): View
    {
        $serviceOrders = ServiceOrder::query()
            ->with(['service.user', 'package'])
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $q = $request->string('q')->trim()->value();
                $query->where(function ($query) use ($q) {
                    $query->where('code', 'like', "%{$q}%")
                        ->orWhereHas('service', function ($query) use ($q) {
                            $query->where('code', 'like', "%{$q}%");
                        });
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('service-orders.index', ['serviceOrders' => $serviceOrders, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('service-orders.create', [
            'packages' => Package::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(ServiceOrderRequest $request): RedirectResponse
    {
        $serviceOrder = $this->serviceOrderService->create($request->validated());

        return redirect()->route('service-orders.index')->with('status', "Order Layanan berhasil ditambahkan. Grandtotal: {$serviceOrder->grandtotal}");
    }

    public function show(ServiceOrder $serviceOrder): View
    {
        $serviceOrder->load(['service.user', 'package', 'plan', 'products', 'receipt']);

        return view('service-orders.show', ['serviceOrder' => $serviceOrder]);
    }

    public function edit(ServiceOrder $serviceOrder): View
    {
        $serviceOrder->load(['service.user', 'package', 'products']);

        return view('service-orders.edit', [
            'serviceOrder' => $serviceOrder,
            'packages' => Package::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function update(ServiceOrderRequest $request, ServiceOrder $serviceOrder): RedirectResponse
    {
        $this->serviceOrderService->update($serviceOrder, $request->validated());

        return redirect()->route('service-orders.index')->with('status', 'Order Layanan berhasil diperbarui.');
    }

    public function destroy(ServiceOrder $serviceOrder): RedirectResponse
    {
        $this->serviceOrderService->delete($serviceOrder);

        return redirect()->route('service-orders.index')->with('status', 'Order Layanan berhasil dihapus.');
    }

    /**
     * Coba lagi membuat Payment Request Xendit untuk Order Layanan yang
     * belum berhasil ditagihkan (invoiced_at masih null) — kasus panggilan
     * Xendit sebelumnya gagal (network/API down), bukan retry setelah
     * invoice expired (itu tidak didukung, lihat CLAUDE.md).
     */
    public function retryReceipt(ServiceOrder $serviceOrder): RedirectResponse
    {
        $this->authorize('retryReceipt', $serviceOrder);

        if ($serviceOrder->invoiced_at || $serviceOrder->settled_at || $serviceOrder->canceled_at) {
            return redirect()->route('service-orders.show', $serviceOrder)->with('status', 'Tagihan sudah pernah berhasil dibuat atau Order Layanan sudah tidak aktif.');
        }

        $this->receiptService->createForServiceOrder($serviceOrder);

        return redirect()->route('service-orders.show', $serviceOrder)->with('status', 'Percobaan membuat tagihan telah dijalankan.');
    }

    public function searchServices(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceOrder::class);

        $q = $request->string('q')->trim()->value();

        $services = Service::query()
            ->with('user')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('code', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%")
                        ->orWhereHas('user', function ($query) use ($q) {
                            $query->where('name', 'like', "%{$q}%")
                                ->orWhere('phone', 'like', "%{$q}%");
                        });
                });
            })
            // Query kosong (klik pertama kali di kolom pencarian) tetap
            // mengembalikan daftar browse — bukan array kosong — sama pola
            // seperti picker customer di form Service.
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (Service $service) => [
                'id' => $service->id,
                'code' => $service->code,
                'address' => Str::limit($service->address, 60),
                'customer_name' => $service->user?->name,
                'customer_phone' => $service->user?->phone,
            ]);

        return response()->json($services);
    }
}
