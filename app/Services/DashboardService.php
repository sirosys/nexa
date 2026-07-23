<?php

namespace App\Services;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Tiap stat digate ke permission modul yang jadi sumber datanya, supaya
     * role yang tidak punya akses ke modul tsb (mis. technician vs data
     * finansial) tidak ikut melihat/menghitungnya sama sekali — bukan cuma
     * disembunyikan di view setelah query tetap jalan.
     *
     * @return array<string, int|float>
     */
    public function stats(User $user): array
    {
        $stats = [];

        if ($user->can('users.view')) {
            $stats['registered_customers'] = User::role('customer')->count();
        }

        if ($user->can('services.view')) {
            $stats['active_services'] = Service::query()->where('status', Service::STATUS_ACTIVE)->count();
        }

        if ($user->can('service_orders.view')) {
            $stats['unpaid_invoices'] = ServiceOrder::query()
                ->whereNotNull('invoiced_at')
                ->whereNull('settled_at')
                ->whereNull('canceled_at')
                ->count();

            $stats['revenue_this_month'] = (float) ServiceOrder::query()
                ->whereNotNull('settled_at')
                ->whereBetween('settled_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('grandtotal');
        }

        if ($user->can('installations.view')) {
            $stats['installation_queue'] = Service::query()
                ->whereIn('status', [Service::STATUS_PENDING_INSTALLATION, Service::STATUS_INSTALLING])
                ->count();
        }

        if ($user->can('dismantles.view')) {
            $stats['dismantle_queue'] = Service::query()
                ->whereIn('status', [Service::STATUS_PENDING_DISMANTLE, Service::STATUS_DISMANTLING])
                ->count();
        }

        return $stats;
    }

    /**
     * Urutan baris mengikuti Service::STATUSES (urutan alur lifecycle),
     * BUKAN diurutkan berdasar jumlah — supaya chart terbaca sebagai funnel
     * operasional (dari pending_payment sampai dismantled) yang urutannya
     * stabil, bukan daftar yang tersusun ulang tiap kali angkanya berubah.
     *
     * @return array<int, array{status: string, label: string, count: int}>
     */
    public function serviceStatusDistribution(): array
    {
        $counts = Service::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(Service::STATUSES)
            ->map(fn (string $status) => [
                'status' => $status,
                'label' => Service::STATUS_LABELS[$status] ?? $status,
                'count' => (int) ($counts[$status] ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, total: float}>
     */
    public function monthlyRevenue(int $months = 6): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = ServiceOrder::query()
            ->whereNotNull('settled_at')
            ->where('settled_at', '>=', $start)
            ->selectRaw('DATE_FORMAT(settled_at, "%Y-%m") as ym, SUM(grandtotal) as total')
            ->groupBy('ym')
            ->pluck('total', 'ym');

        return collect(range(0, $months - 1))
            ->map(function (int $offset) use ($start, $rows) {
                $month = $start->copy()->addMonths($offset);

                return [
                    'label' => $month->translatedFormat('M Y'),
                    'total' => (float) ($rows[$month->format('Y-m')] ?? 0),
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, Service>
     */
    public function recentServices(int $limit = 8): Collection
    {
        return Service::query()
            ->with(['user', 'package'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
