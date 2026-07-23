<?php

namespace App\Http\Controllers;

use App\Http\Requests\ServiceTicketAssignRequest;
use App\Http\Requests\ServiceTicketRequest;
use App\Http\Requests\ServiceTicketResolveRequest;
use App\Models\Service;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Services\ServiceTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;

class ServiceTicketController extends Controller
{
    public function __construct(private readonly ServiceTicketService $serviceTicketService)
    {
        $this->authorizeResource(ServiceTicket::class, 'ticket');
    }

    public function index(Request $request): View
    {
        $tickets = ServiceTicket::query()
            ->with(['service.user'])
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')->value()))
            ->when($request->string('category')->isNotEmpty(), fn ($query) => $query->where('category', $request->string('category')->value()))
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request) {
                $q = $request->string('q')->trim()->value();
                $query->where(function ($query) use ($q) {
                    $query->where('code', 'like', "%{$q}%")
                        ->orWhere('subject', 'like', "%{$q}%");
                });
            })
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'q' => $request->string('q')->value(),
            'status' => $request->string('status')->value(),
            'category' => $request->string('category')->value(),
        ]);
    }

    public function create(): View
    {
        return view('tickets.create');
    }

    public function store(ServiceTicketRequest $request): RedirectResponse
    {
        $ticket = $this->serviceTicketService->create($request->validated());

        return redirect()->route('tickets.show', $ticket)->with('status', 'Tiket berhasil dibuat.');
    }

    public function show(ServiceTicket $ticket): View
    {
        $ticket->load(['service.user', 'assignedTechnician', 'assignedBy', 'solvedBy']);

        return view('tickets.show', [
            'ticket' => $ticket,
            'technicians' => User::role('technician')->orderBy('name')->get(),
        ]);
    }

    public function edit(ServiceTicket $ticket): View
    {
        $ticket->load('service.user');

        return view('tickets.edit', ['ticket' => $ticket]);
    }

    public function update(ServiceTicketRequest $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->serviceTicketService->update($ticket, $request->validated());

        return redirect()->route('tickets.show', $ticket)->with('status', 'Tiket berhasil diperbarui.');
    }

    public function destroy(ServiceTicket $ticket): RedirectResponse
    {
        $this->serviceTicketService->delete($ticket);

        return redirect()->route('tickets.index')->with('status', 'Tiket berhasil dihapus.');
    }

    public function assign(ServiceTicketAssignRequest $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('assignTicket', $ticket);

        $technician = User::findOrFail($request->validated('technician_id'));

        try {
            $this->serviceTicketService->assign($ticket, $technician, Auth::user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tickets.show', $ticket)->with('status', 'Tiket berhasil ditugaskan.');
    }

    public function claim(ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('claimTicket', $ticket);

        try {
            $this->serviceTicketService->claim($ticket, Auth::user());
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tickets.show', $ticket)->with('status', 'Tiket berhasil diambil.');
    }

    public function resolve(ServiceTicketResolveRequest $request, ServiceTicket $ticket): RedirectResponse
    {
        $this->authorize('resolveTicket', $ticket);

        try {
            $this->serviceTicketService->resolve($ticket, Auth::user(), $request->validated('resolution_notes'));
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('tickets.show', $ticket)->with('status', 'Tiket berhasil diselesaikan.');
    }

    public function searchServices(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceTicket::class);

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
            // seperti picker service di form Order Layanan.
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
