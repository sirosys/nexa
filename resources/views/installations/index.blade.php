@php
    $statusBadges = [
        'pending_installation' => ['label' => 'Menunggu Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'installing' => ['label' => 'Sedang Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
    ];
@endphp

<x-app-layout :title="'Instalasi — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Instalasi</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Antrean pemasangan layanan yang sudah dibayar — assign atau klaim teknisi di sini.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg border border-danger/20 bg-danger-light px-4 py-3 text-sm text-danger dark:border-danger/30 dark:bg-danger/10">
            {{ session('error') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Alamat</th>
                        <th class="px-4 py-3">Paket</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Teknisi</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($services as $service)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $service->user?->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($service->address, 40) }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->package?->name }}</td>
                            <td class="px-4 py-3">
                                @php($badge = $statusBadges[$service->status] ?? ['label' => $service->status, 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'])
                                <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $badge['label'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->activation?->installer?->name ?? 'Belum ditugaskan' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <x-row-action :href="route('installations.show', $service)" icon="eye" label="Detail" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Tidak ada job instalasi terbuka.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($services->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $services->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
