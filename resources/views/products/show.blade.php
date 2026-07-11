@php
    $typeBadges = [
        'perangkat' => ['label' => 'Perangkat', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'jasa' => ['label' => 'Jasa', 'class' => 'bg-success-light text-success dark:bg-success/10'],
        'biaya' => ['label' => 'Biaya', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'langganan' => ['label' => 'Langganan', 'class' => 'bg-danger-light text-danger dark:bg-danger/10'],
        'lainnya' => ['label' => 'Lainnya', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
    ];
    $badge = $typeBadges[$product->type] ?? null;
@endphp

<x-app-layout :title="'Detail Produk — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('products.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Produk</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $product->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $product->code }}</p>
        </div>

        <a
            href="{{ route('products.edit', $product) }}"
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
            <x-detail-row label="Kode">{{ $product->code }}</x-detail-row>
            <x-detail-row label="Nama">{{ $product->name }}</x-detail-row>
            <x-detail-row label="Tipe">
                @if ($badge)
                    <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-2.5 py-1 text-xs font-medium">{{ $badge['label'] }}</span>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Harga">Rp{{ number_format((float) $product->price, 0, ',', '.') }}</x-detail-row>
            <x-detail-row label="Satuan">{{ $product->unit ?? '—' }}</x-detail-row>
            <x-detail-row label="Deskripsi">{{ $product->description ?? '—' }}</x-detail-row>
            <x-detail-row label="Ditambahkan">{{ $product->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>
</x-app-layout>
