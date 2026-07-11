<x-app-layout :title="'Detail Coverage — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('coverages.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Coverage</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $coverage->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $coverage->code }}</p>
        </div>

        <a
            href="{{ route('coverages.edit', $coverage) }}"
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
            <x-detail-row label="Kode">{{ $coverage->code }}</x-detail-row>
            <x-detail-row label="Nama">{{ $coverage->name }}</x-detail-row>
            <x-detail-row label="PoP">
                @if ($coverage->pop)
                    <a href="{{ route('pops.show', $coverage->pop) }}" class="font-medium text-primary hover:underline">{{ $coverage->pop->name }}</a>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Deskripsi">{{ $coverage->description ?? '—' }}</x-detail-row>
            <x-detail-row label="Ditambahkan">{{ $coverage->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>
</x-app-layout>
