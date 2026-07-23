<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceActivation;
use App\Models\ServiceDismantle;
use App\Models\ServiceTicket;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ReportService
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    /**
     * Filter di sales.invoiced_at. "Pendapatan" tetap filter sales.settled_at
     * sendiri (bukan invoiced_at) — Sale bisa terbit di satu periode tapi
     * lunas di periode lain, jadi kedua angka sengaja tidak saling terikat
     * satu filter yang sama.
     *
     * @return array{summary: array<string, int|float>, sales: LengthAwarePaginator}
     */
    public function finance(CarbonInterface $from, CarbonInterface $to): array
    {
        $base = fn () => Sale::query()->whereBetween('invoiced_at', [$from, $to]);

        $revenue = (float) Sale::query()
            ->whereNotNull('settled_at')
            ->whereBetween('settled_at', [$from, $to])
            ->sum('grandtotal');

        $unpaidCount = $base()->whereNull('settled_at')->whereNull('canceled_at')->count();
        $unpaidSum = (float) $base()->whereNull('settled_at')->whereNull('canceled_at')->sum('grandtotal');
        $canceledCount = $base()->whereNotNull('canceled_at')->count();
        $issuedCount = $base()->count();

        $sales = $base()
            ->with(['service.user', 'package'])
            ->latest('invoiced_at')
            ->paginate(25, ['*'], 'sales_page')
            ->withQueryString();

        return [
            'summary' => [
                'revenue' => $revenue,
                'unpaid_count' => $unpaidCount,
                'unpaid_sum' => $unpaidSum,
                'canceled_count' => $canceledCount,
                'issued_count' => $issuedCount,
            ],
            'sales' => $sales,
        ];
    }

    /**
     * Antrean/tiket terbuka adalah snapshot current-state — TIDAK ikut
     * filter tanggal (angka itu selalu "sekarang", bukan potongan periode).
     * Bagian "selesai" difilter di completed_at/solved_at masing-masing.
     *
     * @return array{snapshot: array<string, int>, summary: array<string, int|float>, installations: LengthAwarePaginator, dismantles: LengthAwarePaginator, tickets: LengthAwarePaginator}
     */
    public function operations(CarbonInterface $from, CarbonInterface $to): array
    {
        $installationQueue = Service::query()
            ->whereIn('status', [Service::STATUS_PENDING_INSTALLATION, Service::STATUS_INSTALLING])
            ->count();

        $dismantleQueue = Service::query()
            ->whereIn('status', [Service::STATUS_PENDING_DISMANTLE, Service::STATUS_DISMANTLING])
            ->count();

        $openTickets = ServiceTicket::query()
            ->where('status', '!=', ServiceTicket::STATUS_RESOLVED)
            ->count();

        $installationsBase = fn () => ServiceActivation::query()->whereBetween('completed_at', [$from, $to]);
        $dismantlesBase = fn () => ServiceDismantle::query()->whereBetween('completed_at', [$from, $to]);
        $ticketsBase = fn () => ServiceTicket::query()->whereBetween('solved_at', [$from, $to]);

        $avgTicketHours = (float) ($ticketsBase()
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, solved_at)) as avg_hours')
            ->value('avg_hours') ?? 0);

        $installations = $installationsBase()
            ->with(['service', 'installer'])
            ->latest('completed_at')
            ->paginate(15, ['*'], 'installations_page')
            ->withQueryString();

        $dismantles = $dismantlesBase()
            ->with(['service', 'technician'])
            ->latest('completed_at')
            ->paginate(15, ['*'], 'dismantles_page')
            ->withQueryString();

        $tickets = $ticketsBase()
            ->with(['service', 'assignedTechnician'])
            ->latest('solved_at')
            ->paginate(15, ['*'], 'tickets_page')
            ->withQueryString();

        return [
            'snapshot' => [
                'installation_queue' => $installationQueue,
                'dismantle_queue' => $dismantleQueue,
                'open_tickets' => $openTickets,
            ],
            'summary' => [
                'installations_completed' => $installationsBase()->count(),
                'dismantles_completed' => $dismantlesBase()->count(),
                'tickets_resolved' => $ticketsBase()->count(),
                'avg_ticket_resolution_hours' => $avgTicketHours,
            ],
            'installations' => $installations,
            'dismantles' => $dismantles,
            'tickets' => $tickets,
        ];
    }

    /**
     * Distribusi status Service adalah snapshot current-state, reuse
     * langsung DashboardService::serviceStatusDistribution() (bukan filter
     * tanggal) — cuma "Layanan Baru Terdaftar" yang difilter services.created_at.
     *
     * @return array{status_distribution: array<int, array{status: string, label: string, count: int}>, summary: array<string, int>, services: LengthAwarePaginator}
     */
    public function customers(CarbonInterface $from, CarbonInterface $to): array
    {
        $newServicesBase = fn () => Service::query()->whereBetween('created_at', [$from, $to]);

        $services = $newServicesBase()
            ->with(['user', 'package', 'coverage.site'])
            ->latest('created_at')
            ->paginate(25, ['*'], 'services_page')
            ->withQueryString();

        return [
            'status_distribution' => $this->dashboardService->serviceStatusDistribution(),
            'summary' => [
                'new_services' => $newServicesBase()->count(),
            ],
            'services' => $services,
        ];
    }
}
