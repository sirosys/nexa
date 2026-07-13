<x-app-layout :title="'Vendor — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Vendor</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pihak yang menyediakan barang/jasa untuk pengadaan Inventaris.</p>
        </div>

        @can('create', App\Models\Vendor::class)
            <a
                href="{{ route('vendors.create') }}"
                class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
            >
                Tambah Vendor
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('vendors.index') }}">
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
                <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Kontak</th>
                        <th class="px-4 py-3">Telepon</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($vendors as $vendor)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $vendor->code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $vendor->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $vendor->contact_person ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $vendor->phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('vendors.show', $vendor) }}" class="font-medium text-gray-600 hover:underline dark:text-gray-300">Detail</a>
                                    @can('update', $vendor)
                                        <a href="{{ route('vendors.edit', $vendor) }}" class="font-medium text-primary hover:underline">Ubah</a>
                                    @endcan
                                    @can('delete', $vendor)
                                        <form method="POST" action="{{ route('vendors.destroy', $vendor) }}" onsubmit="return confirm('Hapus vendor ini?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="font-medium text-danger hover:underline">Hapus</button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada vendor.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($vendors->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $vendors->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
