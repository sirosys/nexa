<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstallationAssignRequest;
use App\Http\Requests\InstallationCompleteRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\InstallationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class InstallationController extends Controller
{
    public function __construct(
        private readonly InstallationService $installationService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewInstallationQueue', Service::class);

        $services = Service::query()
            ->whereIn('status', [Service::STATUS_PENDING_INSTALLATION, Service::STATUS_INSTALLING])
            ->with(['user', 'coverage', 'package', 'activation.installer'])
            ->latest('id')
            ->paginate(15);

        return view('installations.index', ['services' => $services]);
    }

    public function show(Service $service): View
    {
        $this->authorize('viewInstallationQueue', Service::class);

        $service->load(['user', 'subdistrict', 'coverage', 'package', 'activation.installer', 'activation.assignedBy']);

        return view('installations.show', [
            'service' => $service,
            'technicians' => User::role('technician')->orderBy('name')->get(),
        ]);
    }

    public function assign(InstallationAssignRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('assignInstallation', $service);

        $installer = User::findOrFail($request->validated('installer_id'));

        try {
            $this->installationService->assign($service, $installer, Auth::user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('installations.show', $service)->with('status', 'Instalasi berhasil ditugaskan.');
    }

    public function claim(Service $service): RedirectResponse
    {
        $this->authorize('claimInstallation', $service);

        try {
            $this->installationService->claim($service, Auth::user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('installations.show', $service)->with('status', 'Instalasi berhasil diambil.');
    }

    public function complete(InstallationCompleteRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('completeInstallation', $service);

        try {
            $this->installationService->complete($service, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('installations.show', $service)->with('status', 'Instalasi selesai, layanan sekarang aktif.');
    }
}
