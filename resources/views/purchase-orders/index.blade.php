@php
    use App\Models\PurchaseOrder;

    $statusClasses = [
        PurchaseOrder::STATUS_DRAFT => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
        PurchaseOrder::STATUS_ORDERED => 'bg-info-light text-info dark:bg-info/10',
        PurchaseOrder::STATUS_RECEIVED => 'bg-success-light text-success dark:bg-success/10',
        PurchaseOrder::STATUS_CANCELED => 'bg-danger-light text-danger dark:bg-danger/10',
    ];
@endphp

<x-app-layout :title="'Purchase Order — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Purchase Order</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Pengadaan barang dari vendor ke stok Inventaris.</p>
        </div>

        @can('create', App\Models\PurchaseOrder::class)
            <a
                href="{{ route('purchase-orders.create') }}"
                class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
            >
            <x-icon name="plus" size="4" />
            Tambah Purchase Order
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('purchase-orders.index') }}">
                <input
                    type="text"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Cari kode atau nama vendor..."
                    class="w-full max-w-sm rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Vendor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($purchaseOrders as $purchaseOrder)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $purchaseOrder->code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $purchaseOrder->vendor?->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full {{ $statusClasses[$purchaseOrder->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">
                                    {{ \App\Models\PurchaseOrder::STATUS_LABELS[$purchaseOrder->status] ?? $purchaseOrder->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ \App\Support\Currency::rupiah((float) $purchaseOrder->total) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <x-row-action :href="route('purchase-orders.show', $purchaseOrder)" icon="eye" label="Detail" />
                                    @can('delete', $purchaseOrder)
                                        <form method="POST" action="{{ route('purchase-orders.destroy', $purchaseOrder) }}" onsubmit="return confirm('Hapus Purchase Order ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-row-action icon="trash" label="Hapus" variant="danger" />
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada Purchase Order.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($purchaseOrders->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $purchaseOrders->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
