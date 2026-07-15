<x-app-layout :title="'Paket — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Paket</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Paket bundling produk yang bisa dilanggan pelanggan.</p>
        </div>

        <a
            href="{{ route('packages.create') }}"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
        >
        <x-icon name="plus" size="4" />
        Tambah Paket
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('packages.index') }}">
                <input
                    type="text"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Cari nama atau kode..."
                    class="w-full max-w-sm rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Starter</th>
                        <th class="px-4 py-3">Masa Berlaku</th>
                        <th class="px-4 py-3">Plan</th>
                        <th class="px-4 py-3">Harga</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($packages as $package)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $package->code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $package->name }}</td>
                            <td class="px-4 py-3">
                                @if ($package->is_starter)
                                    <span class="inline-flex items-center rounded-full bg-success-light px-3 py-1 text-[13px] font-semibold text-success dark:bg-success/10">Ya</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[13px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-400">Tidak</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if (! $package->valid_until)
                                    <span class="inline-flex items-center rounded-full bg-info-light px-3 py-1 text-[13px] font-semibold text-info dark:bg-info/10">Unlimited</span>
                                @elseif ($package->isAvailable())
                                    <span class="inline-flex items-center rounded-full bg-success-light px-3 py-1 text-[13px] font-semibold text-success dark:bg-success/10">s.d. {{ $package->valid_until->translatedFormat('d M Y') }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-danger-light px-3 py-1 text-[13px] font-semibold text-danger dark:bg-danger/10">Kadaluarsa</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $package->plan?->name ?? '—' }} &times; {{ $package->plan_qty }} bulan</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">Rp{{ number_format((float) $package->price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <x-row-action :href="route('packages.show', $package)" icon="eye" label="Detail" />
                                    <x-row-action :href="route('packages.edit', $package)" icon="pencil-square" label="Ubah" variant="primary" />
                                    <form method="POST" action="{{ route('packages.destroy', $package) }}" onsubmit="return confirm('Hapus paket ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-row-action icon="trash" label="Hapus" variant="danger" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada paket.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($packages->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $packages->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
