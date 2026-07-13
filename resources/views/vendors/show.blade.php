@php
    use App\Models\PurchaseOrder;

    $statusClasses = [
        PurchaseOrder::STATUS_DRAFT => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400',
        PurchaseOrder::STATUS_ORDERED => 'bg-info-light text-info dark:bg-info/10',
        PurchaseOrder::STATUS_RECEIVED => 'bg-success-light text-success dark:bg-success/10',
        PurchaseOrder::STATUS_CANCELED => 'bg-danger-light text-danger dark:bg-danger/10',
    ];
@endphp

<x-app-layout :title="'Detail Vendor — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('vendors.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Vendor</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $vendor->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $vendor->code }}</p>
        </div>

        @can('update', $vendor)
            <a
                href="{{ route('vendors.edit', $vendor) }}"
                class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
            >
            <x-icon name="pencil-square" size="4" />
            Ubah
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-6">
        <div class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
            <dl>
                <x-detail-row label="Kode">{{ $vendor->code }}</x-detail-row>
                <x-detail-row label="Nama">{{ $vendor->name }}</x-detail-row>
                <x-detail-row label="Kontak Person">{{ $vendor->contact_person ?? '—' }}</x-detail-row>
                <x-detail-row label="Telepon">{{ $vendor->phone ?? '—' }}</x-detail-row>
                <x-detail-row label="Email">{{ $vendor->email ?? '—' }}</x-detail-row>
                <x-detail-row label="Alamat">{{ $vendor->address ?? '—' }}</x-detail-row>
                <x-detail-row label="Catatan">{{ $vendor->notes ?? '—' }}</x-detail-row>
                <x-detail-row label="Ditambahkan">{{ $vendor->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
            </dl>
        </div>

        <div class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
            <div class="border-b border-gray-300 p-4 dark:border-gray-700">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Riwayat Purchase Order</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Kode</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Dibuat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($vendor->purchaseOrders as $purchaseOrder)
                            <tr>
                                <td class="px-4 py-3">
                                    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}" class="font-medium text-primary hover:underline">{{ $purchaseOrder->code }}</a>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full {{ $statusClasses[$purchaseOrder->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">
                                        {{ PurchaseOrder::STATUS_LABELS[$purchaseOrder->status] ?? $purchaseOrder->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ \App\Support\Currency::rupiah((float) $purchaseOrder->total) }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $purchaseOrder->created_at?->locale('id')->translatedFormat('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada Purchase Order untuk vendor ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
