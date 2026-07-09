<?php

namespace App\Http\Controllers;

use App\Http\Requests\PackageRequest;
use App\Models\Package;
use App\Models\Product;
use App\Services\PackageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function __construct(private readonly PackageService $packageService)
    {
        $this->authorizeResource(Package::class, 'package');
    }

    public function index(Request $request): View
    {
        $packages = Package::query()
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

        return view('packages.index', ['packages' => $packages, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('packages.create', ['products' => Product::query()->orderBy('name')->get()]);
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        $this->packageService->create($request->validated());

        return redirect()->route('packages.index')->with('status', 'Paket berhasil ditambahkan.');
    }

    public function edit(Package $package): View
    {
        $package->load('products');

        return view('packages.edit', [
            'package' => $package,
            'products' => Product::query()->orderBy('name')->get(),
        ]);
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $this->packageService->update($package, $request->validated());

        return redirect()->route('packages.index')->with('status', 'Paket berhasil diperbarui.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        $this->packageService->delete($package);

        return redirect()->route('packages.index')->with('status', 'Paket berhasil dihapus.');
    }
}
