<x-app-layout :title="'Detail Order Layanan — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('service-orders.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Order Layanan</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $serviceOrder->code }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $serviceOrder->service?->code }} — {{ $serviceOrder->service?->user?->name }}</p>
        </div>

        <a
            href="{{ route('service-orders.edit', $serviceOrder) }}"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
        >
        <x-icon name="pencil-square" size="4" />
        Ubah
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <dl>
            <x-detail-row label="Kode">{{ $serviceOrder->code }}</x-detail-row>
            <x-detail-row label="Service">
                @if ($serviceOrder->service)
                    <a href="{{ route('services.show', $serviceOrder->service) }}" class="font-medium text-primary hover:underline">{{ $serviceOrder->service->code }}</a>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Paket">
                @if ($serviceOrder->package)
                    <a href="{{ route('packages.show', $serviceOrder->package) }}" class="font-medium text-primary hover:underline">{{ $serviceOrder->package->name }}</a>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Starter">
                @if ($serviceOrder->is_starter)
                    <span class="inline-flex items-center rounded-full bg-success-light px-3 py-1 text-[13px] font-semibold text-success dark:bg-success/10">Ya</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[13px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-400">Tidak</span>
                @endif
            </x-detail-row>
            <x-detail-row label="Perpanjangan">
                @if ($serviceOrder->is_renewal)
                    <span class="inline-flex items-center rounded-full bg-info-light px-3 py-1 text-[13px] font-semibold text-info dark:bg-info/10">Ya</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[13px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-400">Tidak</span>
                @endif
            </x-detail-row>
            <x-detail-row label="Catatan">{{ $serviceOrder->notes ?? '—' }}</x-detail-row>
            <x-detail-row label="Ditagihkan">{{ $serviceOrder->invoiced_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Jatuh Tempo">{{ $serviceOrder->expired_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Lunas">{{ $serviceOrder->settled_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Dibatalkan">{{ $serviceOrder->canceled_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
        </dl>
    </div>

    <div class="mb-6 rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Status Pembayaran</h2>
        </div>
        @if ($serviceOrder->receipt)
            <dl>
                <x-detail-row label="Kode Tagihan">{{ $serviceOrder->receipt->code ?? '—' }}</x-detail-row>
                <x-detail-row label="Status Xendit">
                    <span class="inline-flex items-center rounded-full bg-info-light px-3 py-1 text-[13px] font-semibold text-info dark:bg-info/10">{{ $serviceOrder->receipt->status }}</span>
                </x-detail-row>
                <x-detail-row label="Jumlah">{{ number_format((float) $serviceOrder->receipt->amount, 2) }}</x-detail-row>
                <x-detail-row label="Link Pembayaran">
                    @if ($serviceOrder->receipt->checkout_url)
                        <a href="{{ $serviceOrder->receipt->checkout_url }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">Buka halaman pembayaran &rarr;</a>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">Belum tersedia (invoice belum berhasil dibuat di Xendit)</span>
                    @endif
                </x-detail-row>
            </dl>
        @else
            <div class="p-4 text-sm text-gray-500 dark:text-gray-400">Belum ada tagihan Xendit untuk order layanan ini.</div>
        @endif

        @if (! $serviceOrder->invoiced_at && ! $serviceOrder->settled_at && ! $serviceOrder->canceled_at)
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                <form action="{{ route('service-orders.receipt.retry', $serviceOrder) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]">
                        Buat Tagihan
                    </button>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Tagihan belum berhasil dibuat di Xendit (mis. gagal koneksi). Klik untuk mencoba lagi.</p>
                </form>
            </div>
        @endif
    </div>

    <div class="mb-6 rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Produk</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3">Satuan</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-right">Diskon</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @if ($serviceOrder->plan)
                        <tr class="bg-primary/5 dark:bg-primary/10">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                {{ $serviceOrder->plan->name }}
                                <span class="ml-1 inline-flex items-center rounded-full bg-primary-light px-2 py-0.5 text-[11px] font-semibold text-primary dark:bg-primary/10">Plan</span>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ $serviceOrder->plan_qty }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">bulan</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">—</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">0,00</td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ number_format((float) $serviceOrder->plan_price, 2) }}</td>
                        </tr>
                    @endif
                    @forelse ($serviceOrder->products as $product)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ $product->pivot->quantity }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $product->pivot->unit ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ number_format((float) $product->pivot->price, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ number_format((float) $product->pivot->discount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ number_format(((float) $product->pivot->price * $product->pivot->quantity) - (float) $product->pivot->discount, 2) }}</td>
                        </tr>
                    @empty
                        @unless ($serviceOrder->plan)
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada produk di order layanan ini.</td>
                            </tr>
                        @endunless
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Rincian Harga</h2>
        </div>
        <dl>
            <x-detail-row label="Total">{{ number_format((float) $serviceOrder->total, 2) }}</x-detail-row>
            <x-detail-row label="Diskon">{{ number_format((float) $serviceOrder->discount, 2) }}</x-detail-row>
            <x-detail-row label="Subtotal">{{ number_format((float) $serviceOrder->subtotal, 2) }}</x-detail-row>
            <x-detail-row label="Pajak">{{ number_format((float) $serviceOrder->tax, 2) }}</x-detail-row>
            <x-detail-row label="Biaya Admin">{{ number_format((float) $serviceOrder->admin_fee, 2) }}</x-detail-row>
            <x-detail-row label="Grandtotal" class="font-semibold">{{ number_format((float) $serviceOrder->grandtotal, 2) }}</x-detail-row>
        </dl>
    </div>
</x-app-layout>
