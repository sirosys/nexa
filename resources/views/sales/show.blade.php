<x-app-layout :title="'Detail Sale — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('sales.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Penjualan</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $sale->code }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $sale->service?->code }} — {{ $sale->service?->user?->name }}</p>
        </div>

        <a
            href="{{ route('sales.edit', $sale) }}"
            class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
        >
            Ubah
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <dl>
            <x-detail-row label="Kode">{{ $sale->code }}</x-detail-row>
            <x-detail-row label="Service">
                @if ($sale->service)
                    <a href="{{ route('services.show', $sale->service) }}" class="font-medium text-primary hover:underline">{{ $sale->service->code }}</a>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Paket">
                @if ($sale->package)
                    <a href="{{ route('packages.show', $sale->package) }}" class="font-medium text-primary hover:underline">{{ $sale->package->name }}</a>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Starter">
                @if ($sale->is_starter)
                    <span class="inline-flex items-center rounded-full bg-success-light px-2.5 py-1 text-xs font-medium text-success dark:bg-success/10">Ya</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">Tidak</span>
                @endif
            </x-detail-row>
            <x-detail-row label="Perpanjangan">
                @if ($sale->is_renewal)
                    <span class="inline-flex items-center rounded-full bg-info-light px-2.5 py-1 text-xs font-medium text-info dark:bg-info/10">Ya</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">Tidak</span>
                @endif
            </x-detail-row>
            <x-detail-row label="Catatan">{{ $sale->notes ?? '—' }}</x-detail-row>
            <x-detail-row label="Ditagihkan">{{ $sale->invoiced_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Jatuh Tempo">{{ $sale->expired_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Lunas">{{ $sale->settled_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            <x-detail-row label="Dibatalkan">{{ $sale->canceled_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
        </dl>
    </div>

    <div class="mb-6 rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Status Pembayaran</h2>
        </div>
        @if ($sale->receipt)
            <dl>
                <x-detail-row label="Kode Tagihan">{{ $sale->receipt->code ?? '—' }}</x-detail-row>
                <x-detail-row label="Status Xendit">
                    <span class="inline-flex items-center rounded-full bg-info-light px-2.5 py-1 text-xs font-medium text-info dark:bg-info/10">{{ $sale->receipt->status }}</span>
                </x-detail-row>
                <x-detail-row label="Jumlah">{{ number_format((float) $sale->receipt->amount, 2) }}</x-detail-row>
                <x-detail-row label="Link Pembayaran">
                    @if ($sale->receipt->checkout_url)
                        <a href="{{ $sale->receipt->checkout_url }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">Buka halaman pembayaran &rarr;</a>
                    @else
                        <span class="text-gray-500 dark:text-gray-400">Belum tersedia (invoice belum berhasil dibuat di Xendit)</span>
                    @endif
                </x-detail-row>
            </dl>
        @else
            <div class="p-4 text-sm text-gray-500 dark:text-gray-400">Belum ada tagihan Xendit untuk sale ini.</div>
        @endif

        @if (! $sale->invoiced_at && ! $sale->settled_at && ! $sale->canceled_at)
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                <form action="{{ route('sales.receipt.retry', $sale) }}" method="POST">
                    @csrf
                    <button type="submit" class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active">
                        Buat Tagihan
                    </button>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Tagihan belum berhasil dibuat di Xendit (mis. gagal koneksi). Klik untuk mencoba lagi.</p>
                </form>
            </div>
        @endif
    </div>

    <div class="mb-6 rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Produk</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
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
                    @forelse ($sale->products as $product)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ $product->pivot->quantity }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $product->pivot->unit ?? '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ number_format((float) $product->pivot->price, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ number_format((float) $product->pivot->discount, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ number_format(((float) $product->pivot->price * $product->pivot->quantity) - (float) $product->pivot->discount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada produk di sale ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Rincian Harga</h2>
        </div>
        <dl>
            <x-detail-row label="Total">{{ number_format((float) $sale->total, 2) }}</x-detail-row>
            <x-detail-row label="Diskon">{{ number_format((float) $sale->discount, 2) }}</x-detail-row>
            <x-detail-row label="Subtotal">{{ number_format((float) $sale->subtotal, 2) }}</x-detail-row>
            <x-detail-row label="Pajak">{{ number_format((float) $sale->tax, 2) }}</x-detail-row>
            <x-detail-row label="Biaya Admin">{{ number_format((float) $sale->admin_fee, 2) }}</x-detail-row>
            <x-detail-row label="Grandtotal" class="font-semibold">{{ number_format((float) $sale->grandtotal, 2) }}</x-detail-row>
        </dl>
    </div>
</x-app-layout>
