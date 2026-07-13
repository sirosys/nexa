<x-app-layout :title="'Detail PoP — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('pops.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke PoP</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $pop->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $pop->code }}</p>
        </div>

        <a
            href="{{ route('pops.edit', $pop) }}"
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

    <div class="mb-6 rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <dl>
            <x-detail-row label="Kode">{{ $pop->code }}</x-detail-row>
            <x-detail-row label="Nama">{{ $pop->name }}</x-detail-row>
            <x-detail-row label="Wilayah">
                @if ($pop->subdistrict)
                    {{ $pop->subdistrict->name }}, {{ $pop->subdistrict->district_name }}, {{ $pop->subdistrict->city_name }}, {{ $pop->subdistrict->province_name }}
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Serial">{{ $pop->serial ?? '—' }}</x-detail-row>
            <x-detail-row label="Model">{{ $pop->model ?? '—' }}</x-detail-row>
            <x-detail-row label="Lokasi">{{ $pop->location ?? '—' }}</x-detail-row>
            <x-detail-row label="Host/IP Router">{{ $pop->host ?? '—' }}</x-detail-row>
            <x-detail-row label="Port REST API">{{ $pop->api_port ?? '—' }}</x-detail-row>
            <x-detail-row label="Username API">{{ $pop->api_username ?? '—' }}</x-detail-row>
            <x-detail-row label="Terakhir Online">{{ $pop->last_online_at?->locale('id')->translatedFormat('d F Y, H:i') ?? 'Belum pernah tercatat' }}</x-detail-row>
            <x-detail-row label="Ditambahkan">{{ $pop->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Coverage di PoP Ini</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($pop->coverages as $coverage)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $coverage->code }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('coverages.show', $coverage) }}" class="font-medium text-primary hover:underline">{{ $coverage->name }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada coverage di PoP ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
