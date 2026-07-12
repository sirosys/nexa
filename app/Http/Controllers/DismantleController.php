<?php

namespace App\Http\Controllers;

use App\Http\Requests\DismantleAssignRequest;
use App\Http\Requests\DismantleCompleteRequest;
use App\Models\Service;
use App\Models\User;
use App\Services\DismantleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use RuntimeException;

class DismantleController extends Controller
{
    public function __construct(
        private readonly DismantleService $dismantleService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewDismantleQueue', Service::class);

        $services = Service::query()
            ->whereIn('status', [Service::STATUS_PENDING_DISMANTLE, Service::STATUS_DISMANTLING])
            ->with(['user', 'coverage', 'package', 'dismantle.technician'])
            ->latest('id')
            ->paginate(15);

        return view('dismantles.index', ['services' => $services]);
    }

    public function show(Service $service): View
    {
        $this->authorize('viewDismantleQueue', Service::class);

        $service->load(['user', 'subdistrict', 'coverage', 'package', 'dismantle.technician', 'dismantle.assignedBy', 'dismantle.queuedBy']);

        return view('dismantles.show', [
            'service' => $service,
            'technicians' => User::role('technician')->orderBy('name')->get(),
        ]);
    }

    public function queue(Service $service): RedirectResponse
    {
        $this->authorize('queueDismantle', $service);

        try {
            $this->dismantleService->queue($service, Auth::user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('dismantles.show', $service)->with('status', 'Layanan berhasil diantrekan untuk dismantle.');
    }

    public function assign(DismantleAssignRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('assignDismantle', $service);

        $technician = User::findOrFail($request->validated('technician_id'));

        try {
            $this->dismantleService->assign($service, $technician, Auth::user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('dismantles.show', $service)->with('status', 'Teknisi berhasil ditugaskan.');
    }

    public function claim(Service $service): RedirectResponse
    {
        $this->authorize('claimDismantle', $service);

        try {
            $this->dismantleService->claim($service, Auth::user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('dismantles.show', $service)->with('status', 'Berhasil mengklaim job dismantle ini.');
    }

    public function complete(DismantleCompleteRequest $request, Service $service): RedirectResponse
    {
        $this->authorize('completeDismantle', $service);

        try {
            $this->dismantleService->complete($service, $request->validated());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('dismantles.show', $service)->with('status', 'Dismantle berhasil diselesaikan.');
    }
}
