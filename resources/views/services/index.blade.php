@php
    // Kelas badge ditulis literal (bukan interpolasi) supaya terdeteksi
    // content-scanner Tailwind v4 saat build — sama seperti products/index.
    $statusBadges = [
        'pending_payment' => ['label' => 'Menunggu Pembayaran', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'pending_installation' => ['label' => 'Menunggu Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'active' => ['label' => 'Aktif', 'class' => 'bg-success-light text-success dark:bg-success/10'],
        'suspended' => ['label' => 'Suspend', 'class' => 'bg-danger-light text-danger dark:bg-danger/10'],
        'canceled' => ['label' => 'Dibatalkan', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
        'dismantled' => ['label' => 'Dismantle', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
    ];
@endphp

<x-app-layout :title="'Layanan — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Layanan</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Langganan pelanggan (alamat pemasangan + coverage).</p>
        </div>

        <a
            href="{{ route('services.create') }}"
            class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
        >
            Tambah Service
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('services.index') }}">
                <input
                    type="text"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Cari alamat atau kode..."
                    class="w-full max-w-sm rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Alamat</th>
                        <th class="px-4 py-3">Coverage</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($services as $service)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $service->user?->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($service->address, 40) }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $service->coverage?->name }}</td>
                            <td class="px-4 py-3">
                                @php($badge = $statusBadges[$service->status] ?? ['label' => $service->status, 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'])
                                <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-2.5 py-1 text-xs font-medium">{{ $badge['label'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('services.edit', $service) }}" class="font-medium text-primary hover:underline">Ubah</a>
                                    <form method="POST" action="{{ route('services.destroy', $service) }}" onsubmit="return confirm('Hapus service ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-danger hover:underline">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada service.</td>
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
