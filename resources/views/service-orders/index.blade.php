<x-app-layout :title="'Order Layanan — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Order Layanan</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Transaksi order produk/paket terhadap sebuah service.</p>
        </div>

        <a
            href="{{ route('service-orders.create') }}"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
        >
        <x-icon name="plus" size="4" />
        Tambah Order Layanan
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('service-orders.index') }}">
                <input
                    type="text"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Cari kode order layanan atau kode service..."
                    class="w-full max-w-sm rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Paket</th>
                        <th class="px-4 py-3 text-right">Grandtotal</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($serviceOrders as $serviceOrder)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $serviceOrder->code }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $serviceOrder->service?->code }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $serviceOrder->service?->user?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $serviceOrder->package?->name }}</td>
                            <td class="px-4 py-3 text-right text-gray-900 dark:text-white">{{ number_format((float) $serviceOrder->grandtotal, 2) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <x-row-action :href="route('service-orders.show', $serviceOrder)" icon="eye" label="Detail" />
                                    <x-row-action :href="route('service-orders.edit', $serviceOrder)" icon="pencil-square" label="Ubah" variant="primary" />
                                    <form method="POST" action="{{ route('service-orders.destroy', $serviceOrder) }}" onsubmit="return confirm('Hapus order layanan ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-row-action icon="trash" label="Hapus" variant="danger" />
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada order layanan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($serviceOrders->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $serviceOrders->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
