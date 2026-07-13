@php
    use App\Models\PurchaseOrder;

    $statusClasses = [
        PurchaseOrder::STATUS_DRAFT => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
        PurchaseOrder::STATUS_ORDERED => 'bg-info-light text-info dark:bg-info/10',
        PurchaseOrder::STATUS_RECEIVED => 'bg-success-light text-success dark:bg-success/10',
        PurchaseOrder::STATUS_CANCELED => 'bg-danger-light text-danger dark:bg-danger/10',
    ];

    $hasSerializedItems = $purchaseOrder->inventoryItems->contains(fn ($item) => $item->is_serialized);
@endphp

<x-app-layout :title="'Detail Purchase Order — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('purchase-orders.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Purchase Order</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $purchaseOrder->code }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Vendor: {{ $purchaseOrder->vendor?->name }}</p>
        </div>

        <div class="flex items-center gap-3">
            @if ($purchaseOrder->status === PurchaseOrder::STATUS_DRAFT)
                @can('update', $purchaseOrder)
                    <a
                        href="{{ route('purchase-orders.edit', $purchaseOrder) }}"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 hover:shadow-md dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <x-icon name="pencil-square" size="4" />
                        Ubah
                    </a>
                @endcan
                @can('order', $purchaseOrder)
                    <form method="POST" action="{{ route('purchase-orders.order', $purchaseOrder) }}" onsubmit="return confirm('Tandai Purchase Order ini sebagai dipesan ke vendor?');">
                        @csrf
                        <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]">Tandai Dipesan</button>
                    </form>
                @endcan
            @endif

            @if (in_array($purchaseOrder->status, [PurchaseOrder::STATUS_DRAFT, PurchaseOrder::STATUS_ORDERED], true))
                @can('cancel', $purchaseOrder)
                    <form method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder) }}" onsubmit="return confirm('Batalkan Purchase Order ini?');">
                        @csrf
                        <button type="submit" class="rounded-xl border border-danger/30 px-5 py-2.5 text-sm font-semibold text-danger shadow-sm transition hover:bg-danger-light hover:shadow-md dark:hover:bg-danger/10">Batalkan</button>
                    </form>
                @endcan
            @endif
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
            <div class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                <dl>
                    <x-detail-row label="Kode">{{ $purchaseOrder->code }}</x-detail-row>
                    <x-detail-row label="Vendor">
                        <a href="{{ route('vendors.show', $purchaseOrder->vendor) }}" class="font-medium text-primary hover:underline">{{ $purchaseOrder->vendor?->name }}</a>
                    </x-detail-row>
                    <x-detail-row label="Status">
                        <span class="inline-flex items-center rounded-full {{ $statusClasses[$purchaseOrder->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">
                            {{ PurchaseOrder::STATUS_LABELS[$purchaseOrder->status] ?? $purchaseOrder->status }}
                        </span>
                    </x-detail-row>
                    <x-detail-row label="Total">{{ \App\Support\Currency::rupiah((float) $purchaseOrder->total) }}</x-detail-row>
                    <x-detail-row label="Dipesan">{{ $purchaseOrder->ordered_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
                    <x-detail-row label="Diterima">{{ $purchaseOrder->received_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
                    <x-detail-row label="Dibatalkan">{{ $purchaseOrder->canceled_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
                    <x-detail-row label="Catatan">{{ $purchaseOrder->notes ?? '—' }}</x-detail-row>
                </dl>
            </div>

            <div class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                <div class="border-b border-gray-300 p-4 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Barang Dipesan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Item</th>
                                <th class="px-4 py-3">Pelacakan</th>
                                <th class="px-4 py-3 text-right">Qty</th>
                                <th class="px-4 py-3 text-right">Harga Beli</th>
                                <th class="px-4 py-3 text-right">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse ($purchaseOrder->inventoryItems as $item)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('inventory-items.show', $item) }}" class="font-medium text-primary hover:underline">{{ $item->product?->name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $item->is_serialized ? 'Per Serial' : 'Kuantitas' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ $item->pivot->quantity }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ \App\Support\Currency::rupiah((float) $item->pivot->price) }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ \App\Support\Currency::rupiah((float) $item->pivot->price * $item->pivot->quantity) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada barang.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($purchaseOrder->status === PurchaseOrder::STATUS_ORDERED)
            @can('receive', $purchaseOrder)
                <div class="space-y-6">
                    <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                        <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Terima Barang</h2>
                        <form method="POST" action="{{ route('purchase-orders.receive', $purchaseOrder) }}" class="space-y-4">
                            @csrf

                            @if ($hasSerializedItems)
                                @foreach ($purchaseOrder->inventoryItems as $item)
                                    @if ($item->is_serialized)
                                        <div>
                                            <label class="mb-1.5 block text-xs font-medium text-gray-500 dark:text-gray-400">
                                                {{ $item->product?->name }} — Serial Number (persis {{ $item->pivot->quantity }} baris)
                                            </label>
                                            <textarea
                                                name="serial_numbers[{{ $item->id }}]"
                                                rows="3"
                                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                            ></textarea>
                                            @error("serial_numbers.{$item->id}")
                                                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    @endif
                                @endforeach
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada item per-serial di Purchase Order ini — kuantitas akan langsung ditambahkan ke stok.</p>
                            @endif

                            <button type="submit" class="w-full rounded-xl bg-success px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-success/25 transition hover:bg-success-active hover:shadow-md active:scale-[0.98]">Terima Barang</button>
                        </form>
                    </div>
                </div>
            @endcan
        @endif
    </div>
</x-app-layout>
