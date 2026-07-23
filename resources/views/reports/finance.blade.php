@php
    use App\Support\Currency;
    use App\Support\ServiceOrderStatus;

    // Reuse App\Support\ServiceOrderStatus — dipakai juga oleh API
    // customer-facing (lihat CLAUDE.md "API Customer-Facing"), bukan lagi
    // closure lokal.
    $serviceOrderStatus = fn ($serviceOrder) => ServiceOrderStatus::resolve($serviceOrder);

    // Kelas badge/aksen ditulis literal (bukan interpolasi) supaya terdeteksi
    // content-scanner Tailwind v4 saat build — pola sama dashboard.blade.php.
    $cards = [
        ['label' => 'Total Pendapatan', 'value' => Currency::rupiah($summary['revenue']), 'badge' => 'bg-success-light text-success dark:bg-success/10', 'accent' => 'bg-success', 'icon' => 'banknotes'],
        ['label' => 'Tagihan Belum Lunas', 'value' => number_format($summary['unpaid_count']).' ('.Currency::rupiah($summary['unpaid_sum']).')', 'badge' => 'bg-warning-light text-warning dark:bg-warning/10', 'accent' => 'bg-warning', 'icon' => 'credit-card'],
        ['label' => 'Tagihan Dibatalkan', 'value' => number_format($summary['canceled_count']), 'badge' => 'bg-danger-light text-danger dark:bg-danger/10', 'accent' => 'bg-danger', 'icon' => 'x-circle'],
        ['label' => 'Jumlah Tagihan Terbit', 'value' => number_format($summary['issued_count']), 'badge' => 'bg-primary-light text-primary dark:bg-primary/10', 'accent' => 'bg-primary', 'icon' => 'document-text'],
    ];
@endphp

<x-app-layout :title="'Laporan Keuangan — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Laporan</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan &amp; riwayat keuangan/billing berdasarkan tanggal terbit tagihan.</p>
    </div>

    @include('reports._nav')
    @include('reports._filter')

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($cards as $card)
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                <span class="absolute inset-x-0 top-0 h-1 {{ $card['accent'] }}"></span>
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $card['badge'] }}">
                    <x-icon :name="$card['icon']" size="6" />
                </span>
                <p class="mt-4 text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ $card['value'] }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Riwayat Tagihan</h2>
        </div>

        @if ($serviceOrders->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada tagihan pada periode ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-6 py-3">Kode</th>
                            <th class="px-4 py-3">Pelanggan</th>
                            <th class="px-4 py-3">Paket</th>
                            <th class="px-4 py-3">Tgl Invoice</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Nominal</th>
                            <th class="px-4 py-3">Tgl Lunas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($serviceOrders as $serviceOrder)
                            @php($status = $serviceOrderStatus($serviceOrder))
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3">
                                    <a href="{{ route('service-orders.show', $serviceOrder) }}" class="font-semibold text-primary hover:underline">{{ $serviceOrder->code ?? '—' }}</a>
                                    @if ($serviceOrder->is_renewal)
                                        <span class="ms-1 inline-flex items-center rounded-full bg-info-light px-2 py-0.5 text-[11px] font-semibold text-info dark:bg-info/10">Perpanjangan</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $serviceOrder->service?->user?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $serviceOrder->package?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $serviceOrder->invoiced_at?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full {{ $status['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $status['label'] }}</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ Currency::rupiah($serviceOrder->grandtotal) }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $serviceOrder->settled_at?->translatedFormat('d M Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($serviceOrders->hasPages())
                <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                    {{ $serviceOrders->links() }}
                </div>
            @endif
        @endif
    </div>
</x-app-layout>
