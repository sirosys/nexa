<x-app-layout :title="'Detail Paket — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('packages.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Paket</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $package->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $package->code }}</p>
        </div>

        <a
            href="{{ route('packages.edit', $package) }}"
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
            <x-detail-row label="Kode">{{ $package->code }}</x-detail-row>
            <x-detail-row label="Nama">{{ $package->name }}</x-detail-row>
            <x-detail-row label="Starter">
                @if ($package->is_starter)
                    <span class="inline-flex items-center rounded-full bg-success-light px-2.5 py-1 text-xs font-medium text-success dark:bg-success/10">Ya</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">Tidak</span>
                @endif
            </x-detail-row>
            <x-detail-row label="Durasi">{{ $package->duration_months }} bulan</x-detail-row>
            <x-detail-row label="Harga">Rp{{ number_format((float) $package->price, 0, ',', '.') }}</x-detail-row>
            <x-detail-row label="Deskripsi">{{ $package->description ?? '—' }}</x-detail-row>
            <x-detail-row label="Ditambahkan">{{ $package->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Produk dalam Paket</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
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
