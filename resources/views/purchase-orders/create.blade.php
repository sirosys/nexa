<x-app-layout :title="'Tambah Purchase Order — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Tambah Purchase Order</h1>
    </div>

    <div class="max-w-3xl rounded-2xl border border-gray-300 bg-white p-6 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <form method="POST" action="{{ route('purchase-orders.store') }}">
            @csrf

            @include('purchase-orders._form')

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
                >
                    Simpan sebagai Draf
                </button>
                <a href="{{ route('purchase-orders.index') }}" class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
