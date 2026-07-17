@php
    $typeBadges = [
        'perangkat' => ['label' => 'Perangkat', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'jasa' => ['label' => 'Jasa', 'class' => 'bg-success-light text-success dark:bg-success/10'],
        'biaya' => ['label' => 'Biaya', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'lainnya' => ['label' => 'Lainnya', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
    ];
    $badge = $typeBadges[$product->type] ?? null;
@endphp

<x-app-layout :title="'Detail Produk — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Produk</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $product->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $product->code }}</p>
        </div>

        <a
            href="{{ route('products.edit', $product) }}"
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

    <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <dl>
            <x-detail-row label="Kode">{{ $product->code }}</x-detail-row>
            <x-detail-row label="Nama">{{ $product->name }}</x-detail-row>
            <x-detail-row label="Tipe">
                @if ($badge)
                    <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $badge['label'] }}</span>
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
