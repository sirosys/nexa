@php
    $statusBadges = [
        'pending_payment' => ['label' => 'Menunggu Pembayaran', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'pending_installation' => ['label' => 'Menunggu Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'installing' => ['label' => 'Sedang Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'active' => ['label' => 'Aktif', 'class' => 'bg-success-light text-success dark:bg-success/10'],
        'suspended' => ['label' => 'Suspend', 'class' => 'bg-danger-light text-danger dark:bg-danger/10'],
        'canceled' => ['label' => 'Dibatalkan', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
        'dismantled' => ['label' => 'Dismantle', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
    ];
    $badge = $statusBadges[$service->status] ?? ['label' => $service->status, 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'];
@endphp

<x-app-layout :title="'Detail Service — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('services.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Layanan</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $service->code }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $service->user?->name }}</p>
        </div>

        <a
            href="{{ route('services.edit', $service) }}"
            class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
        >
            Ubah
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <dl>
            <x-detail-row label="Kode">{{ $service->code }}</x-detail-row>
            <x-detail-row label="Status">
                <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-2.5 py-1 text-xs font-medium">{{ $badge['label'] }}</span>
            </x-detail-row>
            <x-detail-row label="Pelanggan">
                @if ($service->user)
                    <a href="{{ route('users.show', $service->user) }}" class="font-medium text-primary hover:underline">{{ $service->user->name }}</a>
                    <span class="text-gray-500 dark:text-gray-400">({{ $service->user->phone }})</span>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Alamat">{{ $service->address }}</x-detail-row>
            <x-detail-row label="Nama Perumahan/Komplek">{{ $service->residential_name ?? '—' }}</x-detail-row>
            <x-detail-row label="RT/RW">{{ $service->rt ?? '—' }} / {{ $service->rw ?? '—' }}</x-detail-row>
            <x-detail-row label="Wilayah">
                @if ($service->subdistrict)
                    {{ $service->subdistrict->name }}, {{ $service->subdistrict->district_name }}, {{ $service->subdistrict->city_name }}, {{ $service->subdistrict->province_name }}
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Coverage">
                @if ($service->coverage)
                    <a href="{{ route('coverages.show', $service->coverage) }}" class="font-medium text-primary hover:underline">{{ $service->coverage->name }}</a>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Paket">
                @if ($service->package)
                    <a href="{{ route('packages.show', $service->package) }}" class="font-medium text-primary hover:underline">{{ $service->package->name }}</a>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="PIN PPPoE">{{ $service->pin }}</x-detail-row>
            @if (in_array($service->status, [\App\Models\Service::STATUS_PENDING_INSTALLATION, \App\Models\Service::STATUS_INSTALLING], true) || $service->activation)
                <x-detail-row label="Instalasi">
                    <a href="{{ route('installations.show', $service) }}" class="font-medium text-primary hover:underline">Lihat detail instalasi</a>
                </x-detail-row>
            @endif
            <x-detail-row label="Diaktifkan">{{ $service->activated_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Berlaku Sampai">{{ $service->expired_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Dibongkar">{{ $service->dismantled_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Dibatalkan">{{ $service->canceled_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Ditambahkan">{{ $service->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>
</x-app-layout>
