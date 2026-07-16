<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickCreateCustomerRequest;
use App\Http\Requests\ServiceRequest;
use App\Models\Coverage;
use App\Models\Package;
use App\Models\Service;
use App\Models\Subdistrict;
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

        // Dipakai modal wizard "Tambah Service" (lihat services/_wizard.blade.php)
        // untuk mengisi ulang label pelanggan/wilayah yang sudah dipilih kalau
        // redirect-back membawa error validasi — tanpa ini staff harus mengulang
        // step 1/3 dari nol tiap kali validasi step berikutnya gagal.
        $oldCustomer = $request->old('user_id')
            ? User::with('userDetails')->find($request->old('user_id'))
            : null;
        $oldSubdistrict = $request->old('subdistrict_id')
            ? Subdistrict::find($request->old('subdistrict_id'))
            : null;

        return view('services.index', [
            'services' => $services,
            'q' => $request->string('q')->value(),
            'coverages' => Coverage::orderBy('name')->get(),
            'packages' => Package::with('plan')->where('is_starter', true)->available()->orderBy('name')->get(),
            'oldCustomerLabel' => $oldCustomer ? "{$oldCustomer->name} ({$oldCustomer->phone})" : '',
            'oldSubdistrictLabel' => $oldSubdistrict
                ? "{$oldSubdistrict->name}, {$oldSubdistrict->district_name}, {$oldSubdistrict->city_name}, {$oldSubdistrict->province_name}"
                : '',
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $service = $this->serviceService->create($request->validated());

        return redirect()->route('services.index')->with('status', "Service berhasil ditambahkan. PIN PPPoE: {$service->pin}");
    }

    public function show(Service $service): View
    {
        $service->load(['user', 'subdistrict', 'coverage', 'package', 'activation']);

        return view('services.show', ['service' => $service]);
    }

    public function edit(Service $service): View
    {
        $service->load(['user', 'subdistrict', 'coverage', 'package']);

        return view('services.edit', [
            'service' => $service,
            'coverages' => Coverage::orderBy('name')->get(),
            'packages' => Package::where('is_starter', true)->available()->orderBy('name')->get(),
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
     * tidak ditemukan di typeahead) — lihat CLAUDE.md "Service". Selalu role
     * customer. NIK & foto KTP diisi LANGSUNG di sini (sejak 2026-07-16,
     * disamakan dengan form "Tambah Pengguna" di /users) — bukan lagi
     * disusul modal "Lengkapi NIK & Foto KTP" terpisah.
     */
    public function storeCustomer(QuickCreateCustomerRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);

        $customer = $this->userService->create([
            ...$request->validated(),
            'role' => 'customer',
        ])->load('userDetails');

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'code' => $customer->code,
            'has_nik' => filled($customer->userDetails?->nik),
            'has_ktp_photo' => filled($customer->userDetails?->ktp_photo),
        ], 201);
    }
}
