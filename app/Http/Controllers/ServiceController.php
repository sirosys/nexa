<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickCreateCustomerRequest;
use App\Http\Requests\ServiceRequest;
use App\Models\Coverage;
use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Services\ServiceService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceService $serviceService,
        private readonly UserService $userService,
    ) {
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

    public function show(Service $service): View
    {
        $service->load(['user', 'subdistrict', 'coverage', 'package']);

        return view('services.show', ['service' => $service]);
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

        $customers = User::role('customer')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($query) use ($q) {
                    $query->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            // Query kosong (klik pertama kali di kolom pencarian) tetap
            // mengembalikan daftar browse — bukan array kosong — supaya
            // picker bisa dibuka lewat klik, bukan cuma lewat mengetik.
            ->with('userDetails')
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'code'])
            ->map(fn (User $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'code' => $customer->code,
                // Dipakai frontend untuk gate "lengkapi NIK & foto KTP"
                // sebelum pelanggan ini bisa dipilih untuk Service baru.
                'has_nik' => filled($customer->userDetails?->nik),
                'has_ktp_photo' => filled($customer->userDetails?->ktp_photo),
            ]);

        return response()->json($customers);
    }

    /**
     * Modal "Tambah Pelanggan Baru" di form Service (dipakai kalau pelanggan
     * tidak ditemukan di typeahead) — lihat CLAUDE.md "Service". Selalu
     * role customer, tanpa NIK/foto KTP (disusul modal "Lengkapi NIK &
     * Foto KTP" terpisah, karena keduanya wajib diisi sebelum Service bisa
     * didaftarkan).
     */
    public function storeCustomer(QuickCreateCustomerRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $customer = $this->userService->create([
            ...$request->validated(),
            'role' => 'customer',
        ]);

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'code' => $customer->code,
            'has_nik' => false,
            'has_ktp_photo' => false,
        ], 201);
    }
}
