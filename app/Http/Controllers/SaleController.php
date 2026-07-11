<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaleRequest;
use App\Models\Package;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Services\ReceiptService;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(
        private readonly SaleService $saleService,
        private readonly ReceiptService $receiptService,
    ) {
        $this->authorizeResource(Sale::class, 'sale');
    }

    public function index(Request $request): View
    {
        $sales = Sale::query()
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

        return view('sales.index', ['sales' => $sales, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('sales.create', [
            'packages' => Package::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function store(SaleRequest $request): RedirectResponse
    {
        $sale = $this->saleService->create($request->validated());

        return redirect()->route('sales.index')->with('status', "Sale berhasil ditambahkan. Grandtotal: {$sale->grandtotal}");
    }

    public function show(Sale $sale): View
    {
        $sale->load(['service.user', 'package', 'products', 'receipt']);

        return view('sales.show', ['sale' => $sale]);
    }

    public function edit(Sale $sale): View
    {
        $sale->load(['service.user', 'package', 'products']);

        return view('sales.edit', [
            'sale' => $sale,
            'packages' => Package::orderBy('name')->get(),
            'products' => Product::orderBy('name')->get(),
        ]);
    }

    public function update(SaleRequest $request, Sale $sale): RedirectResponse
    {
        $this->saleService->update($sale, $request->validated());

        return redirect()->route('sales.index')->with('status', 'Sale berhasil diperbarui.');
    }

    public function destroy(Sale $sale): RedirectResponse
    {
        $this->saleService->delete($sale);

        return redirect()->route('sales.index')->with('status', 'Sale berhasil dihapus.');
    }

    /**
     * Coba lagi membuat Payment Request Xendit untuk Sale yang belum
     * berhasil ditagihkan (invoiced_at masih null) — kasus panggilan
     * Xendit sebelumnya gagal (network/API down), bukan retry setelah
     * invoice expired (itu tidak didukung, lihat CLAUDE.md).
     */
    public function retryReceipt(Sale $sale): RedirectResponse
    {
        $this->authorize('update', $sale);

        if ($sale->invoiced_at || $sale->settled_at || $sale->canceled_at) {
            return redirect()->route('sales.show', $sale)->with('status', 'Tagihan sudah pernah berhasil dibuat atau Sale sudah tidak aktif.');
        }

        $this->receiptService->createForSale($sale);

        return redirect()->route('sales.show', $sale)->with('status', 'Percobaan membuat tagihan telah dijalankan.');
    }

    public function searchServices(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Sale::class);

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
