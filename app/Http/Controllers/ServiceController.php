<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceRequest;
use App\Models\Coverage;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Services\ServiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(private readonly ServiceService $serviceService)
    {
        $this->authorizeResource(Service::class, 'service');
    }

    public function index(Request $request): View
    {
        $services = Service::query()
            ->with(['user', 'coverage'])
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $q = $request->string('q')->trim()->value();
                $query->where(function ($query) use ($q) {
                    $query->where('address', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('services.index', ['services' => $services, 'q' => $request->string('q')->value()]);
    }

    public function create(): View
    {
        return view('services.create', [
            'coverages' => Coverage::orderBy('name')->get(),
            'packages' => Package::where('is_starter', true)->orderBy('name')->get(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = $this->serviceService->create($request->validated());

        return redirect()->route('services.index')->with('status', "Service berhasil ditambahkan. PIN PPPoE: {$service->pin}");
    }

    public function edit(Service $service): View
    {
        $service->load(['user', 'subdistrict', 'coverage', 'package']);

        return view('services.edit', [
            'service' => $service,
            'coverages' => Coverage::orderBy('name')->get(),
            'packages' => Package::where('is_starter', true)->orderBy('name')->get(),
        ]);
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $this->serviceService->update($service, $request->validated());

        return redirect()->route('services.index')->with('status', 'Service berhasil diperbarui.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->serviceService->delete($service);

        return redirect()->route('services.index')->with('status', 'Service berhasil dihapus.');
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $q = $request->string('q')->trim()->value();

        if ($q === '') {
            return response()->json([]);
        }

        $customers = User::role('customer')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            })
            ->limit(20)
            ->get(['id', 'name', 'phone', 'code']);

        return response()->json($customers);
    }
}
