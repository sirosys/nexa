@php
    $unitStatusBadges = \App\Models\InventoryUnit::STATUS_LABELS;
    $unitStatusClasses = [
        'in_stock' => 'bg-success-light text-success dark:bg-success/10',
        'installed' => 'bg-info-light text-info dark:bg-info/10',
    ];
@endphp

<x-app-layout :title="'Detail Item Inventaris — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('inventory-items.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Inventaris</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $item->product?->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $item->code }}</p>
        </div>
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

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <dl>
                    <x-detail-row label="Kode">{{ $item->code }}</x-detail-row>
                    <x-detail-row label="Produk">
                        <a href="{{ route('products.show', $item->product) }}" class="font-medium text-primary hover:underline">{{ $item->product?->name }}</a>
                    </x-detail-row>
                    <x-detail-row label="Pelacakan">{{ $item->is_serialized ? 'Per Serial Number' : 'Kuantitas' }}</x-detail-row>
                    <x-detail-row label="Stok Saat Ini">{{ $item->quantity }}</x-detail-row>
                </dl>
            </div>

            @if ($item->is_serialized)
                <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="border-b border-gray-300 p-4 dark:border-gray-700">
                        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Unit</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Serial Number</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3">Terpasang di Service</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($item->units as $unit)
                                    <tr>
                                        <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $unit->serial_number }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full {{ $unitStatusClasses[$unit->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-2.5 py-1 text-xs font-medium">
                                                {{ $unitStatusBadges[$unit->status] ?? $unit->status }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                            @if ($unit->service_id)
                                                <a href="{{ route('services.show', $unit->service_id) }}" class="text-primary hover:underline">{{ $unit->service?->code }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada unit.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-300 p-4 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Riwayat Pergerakan Stok</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">Tipe</th>
                                <th class="px-4 py-3 text-right">Kuantitas</th>
                                <th class="px-4 py-3">Catatan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($item->movements as $movement)
                                <tr>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $movement->created_at?->locale('id')->translatedFormat('d M Y, H:i') }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">{{ \App\Models\InventoryMovement::TYPE_LABELS[$movement->type] ?? $movement->type }}</td>
                                    <td class="px-4 py-3 text-right {{ $movement->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                        {{ $movement->notes ?? '—' }}
                                        @if ($movement->service)
                                            <a href="{{ route('services.show', $movement->service) }}" class="text-primary hover:underline">({{ $movement->service->code }})</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada pergerakan stok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            @can('update', $item)
                <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Tambah Stok</h2>
                    <form method="POST" action="{{ route('inventory-items.stock-in', $item) }}" class="space-y-3">
                        @csrf
                        @if ($item->is_serialized)
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Serial Number (satu per baris)</label>
                                <textarea name="serial_numbers" rows="4" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"></textarea>
                                @error('serial_numbers')
                                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Kuantitas</label>
                                <input type="number" name="quantity" min="1" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">
                                @error('quantity')
                                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Catatan</label>
                            <input type="text" name="notes" placeholder="mis. pembelian dari vendor X" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500">
                        </div>
                        <button type="submit" class="w-full rounded-lg bg-success px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-success-active">Tambah Stok</button>
                    </form>
                </div>

                @unless ($item->is_serialized)
                    <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Sesuaikan Stok</h2>
                        <form method="POST" action="{{ route('inventory-items.adjust', $item) }}" class="space-y-3">
                            @csrf
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Perubahan (boleh negatif)</label>
                                <input type="number" name="delta" placeholder="mis. -1 untuk barang rusak" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500">
                                @error('delta')
                                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">Alasan</label>
                                <input type="text" name="notes" required class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">
                                @error('notes')
                                    <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit" class="w-full rounded-lg bg-warning px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-warning-active">Sesuaikan</button>
                        </form>
                    </div>
                @endunless
            @endcan
        </div>
    </div>
</x-app-layout>
