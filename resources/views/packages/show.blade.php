<x-app-layout :title="'Detail Paket — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('packages.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Paket</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $package->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $package->code }}</p>
        </div>

        <a
            href="{{ route('packages.edit', $package) }}"
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

    <div class="mb-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <dl>
            <x-detail-row label="Kode">{{ $package->code }}</x-detail-row>
            <x-detail-row label="Nama">{{ $package->name }}</x-detail-row>
            <x-detail-row label="Starter">
                @if ($package->is_starter)
                    <span class="inline-flex items-center rounded-full bg-success-light px-3 py-1 text-[13px] font-semibold text-success dark:bg-success/10">Ya</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[13px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-400">Tidak</span>
                @endif
            </x-detail-row>
            <x-detail-row label="Plan">
                @if ($package->plan)
                    <a href="{{ route('plans.show', $package->plan) }}" class="font-medium text-primary hover:underline">{{ $package->plan->name }}</a>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Durasi Plan">{{ $package->plan_qty }} bulan</x-detail-row>
            <x-detail-row label="Harga Plan di Paket Ini">Rp{{ number_format((float) $package->plan_price, 0, ',', '.') }}</x-detail-row>
            <x-detail-row label="Harga Paket">Rp{{ number_format((float) $package->price, 0, ',', '.') }}</x-detail-row>
            <x-detail-row label="Deskripsi">{{ $package->description ?? '—' }}</x-detail-row>
            <x-detail-row label="Ditambahkan">{{ $package->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Produk Lain dalam Paket</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($package->products as $product)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $product->name }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">{{ $product->pivot->quantity }}</td>
                            <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">Rp{{ number_format((float) $product->pivot->price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">Rp{{ number_format((float) $product->pivot->price * $product->pivot->quantity, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada produk di paket ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
