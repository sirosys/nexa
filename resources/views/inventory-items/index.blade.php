<x-app-layout :title="'Inventaris — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Inventaris</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stok perangkat di gudang pusat.</p>
        </div>

        @can('create', App\Models\InventoryItem::class)
            <a
                href="{{ route('inventory-items.create') }}"
                class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
            >
                Tambah Item
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3">Pelacakan</th>
                        <th class="px-4 py-3 text-right">Stok</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $item->code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $item->product?->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                @if ($item->is_serialized)
                                    <span class="inline-flex items-center rounded-full bg-info-light px-2.5 py-1 text-xs font-medium text-info dark:bg-info/10">Per Serial</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">Kuantitas</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ $item->quantity }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('inventory-items.show', $item) }}" class="font-medium text-gray-600 hover:underline dark:text-gray-300">Detail</a>
                                    @can('delete', $item)
                                        <form method="POST" action="{{ route('inventory-items.destroy', $item) }}" onsubmit="return confirm('Hapus item inventaris ini?');">
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
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada item inventaris.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($items->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $items->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
