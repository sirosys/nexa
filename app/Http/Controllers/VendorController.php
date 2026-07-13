<?php

namespace App\Http\Controllers;

use App\Http\Requests\VendorRequest;
use App\Models\Vendor;
use App\Services\VendorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendorController extends Controller
{
    public function __construct(private readonly VendorService $vendorService)
    {
        $this->authorizeResource(Vendor::class, 'vendor');
    }

    public function index(Request $request): View
    {
        $vendors = Vendor::query()
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $q = $request->string('q')->trim()->value();
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('vendors.index', ['vendors' => $vendors, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('vendors.create');
    }

    public function store(VendorRequest $request): RedirectResponse
    {
        $this->vendorService->create($request->validated());

        return redirect()->route('vendors.index')->with('status', 'Vendor berhasil ditambahkan.');
    }

    public function show(Vendor $vendor): View
    {
        $vendor->load(['purchaseOrders' => fn ($query) => $query->latest('id')]);

        return view('vendors.show', ['vendor' => $vendor]);
    }

    public function edit(Vendor $vendor): View
    {
        return view('vendors.edit', ['vendor' => $vendor]);
    }

    public function update(VendorRequest $request, Vendor $vendor): RedirectResponse
    {
        $this->vendorService->update($vendor, $request->validated());

        return redirect()->route('vendors.index')->with('status', 'Vendor berhasil diperbarui.');
    }

    public function destroy(Vendor $vendor): RedirectResponse
    {
        $this->vendorService->delete($vendor);

        return redirect()->route('vendors.index')->with('status', 'Vendor berhasil dihapus.');
    }
}
