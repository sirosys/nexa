<x-app-layout :title="'Detail Plan — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('plans.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Plan</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $plan->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $plan->code }}</p>
        </div>

        <a
            href="{{ route('plans.edit', $plan) }}"
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

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <dl>
            <x-detail-row label="Kode">{{ $plan->code }}</x-detail-row>
            <x-detail-row label="Nama">{{ $plan->name }}</x-detail-row>
            <x-detail-row label="Harga per Bulan">Rp{{ number_format((float) $plan->price, 0, ',', '.') }}</x-detail-row>
            <x-detail-row label="Deskripsi">{{ $plan->description ?? '—' }}</x-detail-row>
            <x-detail-row label="Ditambahkan">{{ $plan->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>
</x-app-layout>
