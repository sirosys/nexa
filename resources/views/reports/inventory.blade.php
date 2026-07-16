@php
    use App\Models\InventoryMovement;
    use App\Models\PurchaseOrder;
    use App\Support\Currency;

    $summaryCards = [
        ['label' => 'Total Masuk', 'value' => number_format($summary['total_in']), 'badge' => 'bg-success-light text-success dark:bg-success/10', 'accent' => 'bg-success', 'icon' => 'arrow-down-tray'],
        ['label' => 'Total Keluar', 'value' => number_format($summary['total_out']), 'badge' => 'bg-danger-light text-danger dark:bg-danger/10', 'accent' => 'bg-danger', 'icon' => 'arrow-up-tray'],
        ['label' => 'Jumlah Penyesuaian', 'value' => number_format($summary['adjustment_count']), 'badge' => 'bg-info-light text-info dark:bg-info/10', 'accent' => 'bg-info', 'icon' => 'clipboard-document-list'],
    ];

    $poCards = [
        ['label' => 'PO Dibuat', 'value' => number_format($summary['po_ordered_count']).' ('.Currency::rupiah($summary['po_ordered_sum']).')', 'badge' => 'bg-primary-light text-primary dark:bg-primary/10', 'accent' => 'bg-primary', 'icon' => 'truck'],
        ['label' => 'PO Diterima', 'value' => number_format($summary['po_received_count']), 'badge' => 'bg-success-light text-success dark:bg-success/10', 'accent' => 'bg-success', 'icon' => 'truck'],
        ['label' => 'PO Dibatalkan', 'value' => number_format($summary['po_canceled_count']), 'badge' => 'bg-danger-light text-danger dark:bg-danger/10', 'accent' => 'bg-danger', 'icon' => 'x-circle'],
    ];

    $poStatusBadges = [
        PurchaseOrder::STATUS_DRAFT => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
        PurchaseOrder::STATUS_ORDERED => 'bg-info-light text-info dark:bg-info/10',
        PurchaseOrder::STATUS_RECEIVED => 'bg-success-light text-success dark:bg-success/10',
        PurchaseOrder::STATUS_CANCELED => 'bg-danger-light text-danger dark:bg-danger/10',
    ];
@endphp

<x-app-layout :title="'Laporan Inventaris — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Laporan</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Stok saat ini &amp; pergerakan/pengadaan pada periode terpilih.</p>
    </div>

    @include('reports._nav')

    <div class="mb-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Stok Saat Ini</h2>
            <p class="text-xs text-gray-400 dark:text-gray-500">Tidak terpengaruh filter tanggal</p>
        </div>

        @if ($current_stock->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Belum ada item inventaris.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-6 py-3">Kode</th>
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Stok</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($current_stock as $item)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3">
                                    <a href="{{ route('inventory-items.show', $item) }}" class="font-semibold text-primary hover:underline">{{ $item->code }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $item->product?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $item->is_serialized ? 'Bersepasang Serial' : 'Kuantitas' }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ number_format($item->quantity) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('reports._filter')

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($summaryCards as $card)
            <div class="relative overflow-hidden rounded-2xl border border-gray-300 bg-white p-5 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                <span class="absolute inset-x-0 top-0 h-1 {{ $card['accent'] }}"></span>
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $card['badge'] }}">
                    <x-icon :name="$card['icon']" size="6" />
                </span>
                <p class="mt-4 text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ $card['value'] }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Riwayat Pergerakan Stok</h2>
        </div>

        @if ($movements->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada pergerakan stok pada periode ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-6 py-3">Tanggal</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Tipe</th>
                            <th class="px-4 py-3">Qty</th>
                            <th class="px-4 py-3">Terkait</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($movements as $movement)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $movement->created_at?->translatedFormat('d M Y H:i') }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $movement->item?->product?->name ?? $movement->item?->code ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ InventoryMovement::TYPE_LABELS[$movement->type] ?? $movement->type }}</td>
                                <td class="px-4 py-3 font-semibold {{ $movement->quantity >= 0 ? 'text-success' : 'text-danger' }}">{{ $movement->quantity >= 0 ? '+' : '' }}{{ number_format($movement->quantity) }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                    @if ($movement->service)
                                        <a href="{{ route('services.show', $movement->service) }}" class="text-primary hover:underline">{{ $movement->service->code }}</a>
                                    @elseif ($movement->purchaseOrder)
                                        <a href="{{ route('purchase-orders.show', $movement->purchaseOrder) }}" class="text-primary hover:underline">{{ $movement->purchaseOrder->code }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($movements->hasPages())
                <div class="border-t border-gray-300 p-4 dark:border-gray-700">{{ $movements->links() }}</div>
            @endif
        @endif
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($poCards as $card)
            <div class="relative overflow-hidden rounded-2xl border border-gray-300 bg-white p-5 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                <span class="absolute inset-x-0 top-0 h-1 {{ $card['accent'] }}"></span>
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $card['badge'] }}">
                    <x-icon :name="$card['icon']" size="6" />
                </span>
                <p class="mt-4 text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ $card['value'] }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Purchase Order</h2>
        </div>

        @if ($purchase_orders->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada Purchase Order pada periode ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-6 py-3">Kode</th>
                            <th class="px-4 py-3">Vendor</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Total</th>
                            <th class="px-4 py-3">Tgl Order</th>
                            <th class="px-4 py-3">Tgl Diterima</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($purchase_orders as $po)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3">
                                    <a href="{{ route('purchase-orders.show', $po) }}" class="font-semibold text-primary hover:underline">{{ $po->code }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $po->vendor?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full {{ $poStatusBadges[$po->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">{{ PurchaseOrder::STATUS_LABELS[$po->status] ?? $po->status }}</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ Currency::rupiah($po->total) }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $po->ordered_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $po->received_at?->translatedFormat('d M Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($purchase_orders->hasPages())
                <div class="border-t border-gray-300 p-4 dark:border-gray-700">{{ $purchase_orders->links() }}</div>
            @endif
        @endif
    </div>
</x-app-layout>
