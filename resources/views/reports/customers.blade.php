@php
    // Sama persis daftar di dashboard.blade.php/services/index.blade.php — belum
    // ada helper bersama untuk ini di project (lihat CLAUDE.md), duplikasi lokal
    // konsisten dengan pola yang sudah ada di modul lain.
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

    $maxDistribution = max(1, collect($status_distribution)->max('count'));
@endphp

<x-app-layout :title="'Laporan Pelanggan & Layanan — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Laporan</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Distribusi status layanan saat ini &amp; pendaftaran baru pada periode terpilih.</p>
    </div>

    @include('reports._nav')

    <div class="mb-6 rounded-2xl border border-gray-300 bg-white p-6 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="flex items-center gap-3">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-light text-primary dark:bg-primary/10">
                <x-icon name="wifi" size="5" />
            </span>
            <div>
                <h2 class="text-base font-bold text-gray-900 dark:text-white">Distribusi Status Layanan (sekarang)</h2>
                <p class="text-xs text-gray-400 dark:text-gray-500">Jumlah layanan per tahap siklus hidup, tidak terpengaruh filter tanggal</p>
            </div>
        </div>

        <div class="mt-4 space-y-2">
            @foreach ($status_distribution as $row)
                <div class="flex items-center gap-3">
                    <span class="w-40 shrink-0 truncate text-sm text-gray-600 dark:text-gray-300">{{ $row['label'] }}</span>
                    <div class="h-2.5 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-full rounded-full bg-primary" style="width: {{ $row['count'] === 0 ? 0 : max(4, round($row['count'] / $maxDistribution * 100)) }}%"></div>
                    </div>
                    <span class="w-10 shrink-0 text-right text-sm font-semibold text-gray-900 dark:text-white">{{ $row['count'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @include('reports._filter')

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="relative overflow-hidden rounded-2xl border border-gray-300 bg-white p-5 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
            <span class="absolute inset-x-0 top-0 h-1 bg-primary"></span>
            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary-light text-primary dark:bg-primary/10">
                <x-icon name="users" size="6" />
            </span>
            <p class="mt-4 text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ number_format($summary['new_services']) }}</p>
            <p class="mt-1 truncate text-sm font-semibold text-gray-500 dark:text-gray-400">Layanan Baru Terdaftar</p>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Layanan Baru Terdaftar</h2>
        </div>

        @if ($services->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada layanan baru terdaftar pada periode ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-6 py-3">Kode</th>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3">Paket</th>
                            <th class="px-4 py-3">Wilayah</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Tgl Daftar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($services as $service)
                            @php($badge = $statusBadges[$service->status] ?? ['label' => $service->status, 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'])
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3">
                                    <a href="{{ route('services.show', $service) }}" class="font-semibold text-primary hover:underline">{{ $service->code }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $service->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->package?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->coverage?->name ?? '—' }}{{ $service->coverage?->pop ? ' — '.$service->coverage->pop->name : '' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $badge['label'] }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->created_at?->translatedFormat('d M Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($services->hasPages())
                <div class="border-t border-gray-300 p-4 dark:border-gray-700">{{ $services->links() }}</div>
            @endif
        @endif
    </div>
</x-app-layout>
