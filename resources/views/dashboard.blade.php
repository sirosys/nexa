@php
    use App\Support\Currency;

    // Kelas badge ditulis literal (bukan interpolasi) supaya terdeteksi Tailwind saat build.
    $statCards = [
        ['label' => 'Pelanggan Terdaftar', 'value' => number_format($stats['registered_customers']), 'badge' => 'bg-primary-light text-primary dark:bg-primary/10'],
        ['label' => 'Layanan Aktif', 'value' => number_format($stats['active_services']), 'badge' => 'bg-success-light text-success dark:bg-success/10'],
        ['label' => 'Tagihan Belum Lunas', 'value' => number_format($stats['unpaid_invoices']), 'badge' => 'bg-warning-light text-warning dark:bg-warning/10'],
        ['label' => 'Pendapatan Bulan Ini', 'value' => Currency::rupiah($stats['revenue_this_month']), 'badge' => 'bg-info-light text-info dark:bg-info/10'],
        ['label' => 'Antrean Instalasi', 'value' => number_format($stats['installation_queue']), 'badge' => 'bg-primary-light text-primary dark:bg-primary/10'],
        ['label' => 'Antrean Dismantle', 'value' => number_format($stats['dismantle_queue']), 'badge' => 'bg-danger-light text-danger dark:bg-danger/10'],
    ];
@endphp

<x-app-layout :title="'Dashboard — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan operasional NEXA.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($statCards as $stat)
            <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <span class="inline-flex items-center rounded-full {{ $stat['badge'] }} px-2.5 py-1 text-xs font-medium">
                    {{ $stat['label'] }}
                </span>
                <p class="mt-4 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-300 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Distribusi Status Layanan</h2>
            <div
                id="service-status-chart"
                class="mt-4"
                data-chart="{{ json_encode($statusDistribution) }}"
            ></div>
        </div>

        <div class="rounded-2xl border border-gray-300 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Pendapatan 6 Bulan Terakhir</h2>
            <div
                id="revenue-chart"
                class="mt-4"
                data-chart="{{ json_encode($monthlyRevenue) }}"
            ></div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-300 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Layanan Terbaru</h2>

        @if ($recentServices->isEmpty())
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Belum ada layanan yang terdaftar.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="pb-2 font-medium">Kode</th>
                            <th class="pb-2 font-medium">Pelanggan</th>
                            <th class="pb-2 font-medium">Paket</th>
                            <th class="pb-2 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentServices as $service)
                            <tr class="border-b border-gray-100 last:border-0 dark:border-gray-800">
                                <td class="py-2">
                                    <a href="{{ route('services.show', $service) }}" class="text-primary hover:underline">{{ $service->code }}</a>
                                </td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $service->user?->name ?? '—' }}</td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ $service->package?->name ?? '—' }}</td>
                                <td class="py-2 text-gray-700 dark:text-gray-300">{{ \App\Models\Service::STATUS_LABELS[$service->status] ?? $service->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-app-layout>
