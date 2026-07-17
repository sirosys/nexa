@php
    $statusClasses = [
        \App\Models\Site::STATUS_ONLINE => 'bg-success-light text-success dark:bg-success/10',
        \App\Models\Site::STATUS_OFFLINE => 'bg-danger-light text-danger dark:bg-danger/10',
        \App\Models\Site::STATUS_UNKNOWN => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
    ];
@endphp

<x-app-layout :title="'Detail Site — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('sites.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Site</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $site->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $site->code }}</p>
        </div>

        <a
            href="{{ route('sites.edit', $site) }}"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
        >
        <x-icon name="pencil-square" size="4" />
        Ubah
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <dl>
            <x-detail-row label="Kode">{{ $site->code }}</x-detail-row>
            <x-detail-row label="Nama">{{ $site->name }}</x-detail-row>
            <x-detail-row label="Wilayah">
                @if ($site->subdistrict)
                    {{ $site->subdistrict->name }}, {{ $site->subdistrict->district_name }}, {{ $site->subdistrict->city_name }}, {{ $site->subdistrict->province_name }}
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Serial">{{ $site->serial ?? '—' }}</x-detail-row>
            <x-detail-row label="Model">{{ $site->model ?? '—' }}</x-detail-row>
            <x-detail-row label="Lokasi">{{ $site->location ?? '—' }}</x-detail-row>
            <x-detail-row label="Host/IP Router">{{ $site->host ?? '—' }}</x-detail-row>
            <x-detail-row label="Port REST API">{{ $site->api_port ?? '—' }}</x-detail-row>
            <x-detail-row label="Username API">{{ $site->api_username ?? '—' }}</x-detail-row>
            <x-detail-row label="Status">
                <span class="inline-flex items-center rounded-full {{ $statusClasses[$site->status] ?? $statusClasses[\App\Models\Site::STATUS_UNKNOWN] }} px-3 py-1 text-[13px] font-semibold">
                    {{ \App\Models\Site::STATUS_LABELS[$site->status] ?? $site->status }}
                </span>
            </x-detail-row>
            <x-detail-row label="Terakhir Online">{{ $site->last_online_at?->locale('id')->translatedFormat('d F Y, H:i') ?? 'Belum pernah tercatat' }}</x-detail-row>
            <x-detail-row label="Ditambahkan">{{ $site->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Coverage di Site Ini</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($site->coverages as $coverage)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $coverage->code }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('coverages.show', $coverage) }}" class="font-medium text-primary hover:underline">{{ $coverage->name }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada coverage di Site ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
