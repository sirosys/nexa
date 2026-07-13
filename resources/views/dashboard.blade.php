@php
    use App\Support\Currency;

    // Kelas badge/aksen ditulis literal (bukan interpolasi) supaya terdeteksi Tailwind saat build.
    // Definisi lengkap tiap kartu yang MUNGKIN tampil — dipangkas ke $stats
    // yang benar-benar dikirim controller (sudah digate per permission di
    // DashboardService::stats()), supaya role yang tidak berwenang atas
    // modul terkait (mis. technician vs data finansial) tidak melihat
    // kartu itu sama sekali.
    $statCardDefinitions = [
        'registered_customers' => ['label' => 'Pelanggan Terdaftar', 'badge' => 'bg-primary-light text-primary dark:bg-primary/10', 'accent' => 'bg-primary', 'icon' => 'users', 'format' => 'number'],
        'active_services' => ['label' => 'Layanan Aktif', 'badge' => 'bg-success-light text-success dark:bg-success/10', 'accent' => 'bg-success', 'icon' => 'wifi', 'format' => 'number'],
        'unpaid_invoices' => ['label' => 'Tagihan Belum Lunas', 'badge' => 'bg-warning-light text-warning dark:bg-warning/10', 'accent' => 'bg-warning', 'icon' => 'credit-card', 'format' => 'number'],
        'revenue_this_month' => ['label' => 'Pendapatan Bulan Ini', 'badge' => 'bg-info-light text-info dark:bg-info/10', 'accent' => 'bg-info', 'icon' => 'banknotes', 'format' => 'currency'],
        'installation_queue' => ['label' => 'Antrean Instalasi', 'badge' => 'bg-primary-light text-primary dark:bg-primary/10', 'accent' => 'bg-primary', 'icon' => 'wrench-screwdriver', 'format' => 'number'],
        'dismantle_queue' => ['label' => 'Antrean Dismantle', 'badge' => 'bg-danger-light text-danger dark:bg-danger/10', 'accent' => 'bg-danger', 'icon' => 'bolt-slash', 'format' => 'number'],
    ];

    $statCards = collect($statCardDefinitions)
        ->filter(fn ($definition, $key) => array_key_exists($key, $stats))
        ->map(fn ($definition, $key) => [
            'label' => $definition['label'],
            'badge' => $definition['badge'],
            'accent' => $definition['accent'],
            'icon' => $definition['icon'],
            'value' => $definition['format'] === 'currency' ? Currency::rupiah($stats[$key]) : number_format($stats[$key]),
        ])
        ->values();

    // Sama persis daftar di services/index.blade.php/show.blade.php — belum ada
    // helper bersama untuk ini di project (lihat CLAUDE.md), jadi duplikasi
    // lokal konsisten dengan pola yang sudah ada di modul lain.
    $statusBadges = [
        'pending_payment' => ['label' => 'Menunggu Pembayaran', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'pending_installation' => ['label' => 'Menunggu Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'installing' => ['label' => 'Sedang Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'active' => ['label' => 'Aktif', 'class' => 'bg-success-light text-success dark:bg-success/10'],
        'suspended' => ['label' => 'Suspend', 'class' => 'bg-danger-light text-danger dark:bg-danger/10'],
        'canceled' => ['label' => 'Dibatalkan', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
        'pending_dismantle' => ['label' => 'Antre Dismantle', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'dismantling' => ['label' => 'Sedang Dismantle', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'dismantled' => ['label' => 'Dibongkar', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
    ];
@endphp

<x-app-layout :title="'Dashboard — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan operasional NEXA.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($statCards as $stat)
            <div class="relative overflow-hidden rounded-2xl border border-gray-300 bg-white p-5 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                <span class="absolute inset-x-0 top-0 h-1 {{ $stat['accent'] }}"></span>
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $stat['badge'] }}">
                    <x-icon :name="$stat['icon']" size="6" />
                </span>
                <p class="mt-4 text-3xl font-black tracking-tight text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $stat['label'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($statusDistribution !== null || $monthlyRevenue !== null)
        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
            @if ($statusDistribution !== null)
                <div class="rounded-2xl border border-gray-300 bg-white p-6 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-light text-primary dark:bg-primary/10">
                            <x-icon name="chart-bar" size="5" />
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">Distribusi Status Layanan</h2>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Jumlah layanan per tahap siklus hidup</p>
                        </div>
                    </div>
                    <div
                        id="service-status-chart"
                        class="mt-4"
                        data-chart="{{ json_encode($statusDistribution) }}"
                    ></div>
                </div>
            @endif

            @if ($monthlyRevenue !== null)
                <div class="rounded-2xl border border-gray-300 bg-white p-6 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-success-light text-success dark:bg-success/10">
                            <x-icon name="banknotes" size="5" />
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-white">Pendapatan 6 Bulan Terakhir</h2>
                            <p class="text-xs text-gray-400 dark:text-gray-500">Total tagihan yang lunas per bulan</p>
                        </div>
                    </div>
                    <div
                        id="revenue-chart"
                        class="mt-4"
                        data-chart="{{ json_encode($monthlyRevenue) }}"
                    ></div>
                </div>
            @endif
        </div>
    @endif

    @if ($recentServices !== null)
        <div class="mt-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-6 py-4 dark:border-gray-700">
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Layanan Terbaru</h2>
                <a href="{{ route('services.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">
                    Lihat Semua
                    <x-icon name="chevron-right" size="4" />
                </a>
            </div>

            @if ($recentServices->isEmpty())
                <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada layanan yang terdaftar.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <th class="px-6 py-3">Pelanggan</th>
                                <th class="px-6 py-3">Kode</th>
                                <th class="px-6 py-3">Paket</th>
                                <th class="px-6 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($recentServices as $service)
                                @php($badge = $statusBadges[$service->status] ?? ['label' => $service->status, 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'])
                                <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">
                                                {{ strtoupper(substr($service->user?->name ?? '?', 0, 1)) }}
                                            </span>
                                            <span class="font-medium text-gray-900 dark:text-white">{{ $service->user?->name ?? '—' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3">
                                        <a href="{{ route('services.show', $service) }}" class="font-semibold text-primary hover:underline">{{ $service->code }}</a>
                                    </td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $service->package?->name ?? '—' }}</td>
                                    <td class="px-6 py-3">
                                        <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $badge['label'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
