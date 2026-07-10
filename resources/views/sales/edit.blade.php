<x-app-layout :title="'Ubah Sale — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Ubah Sale</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kode: {{ $sale->code }}</p>
    </div>

    <div class="max-w-3xl rounded-2xl border border-gray-300 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <form method="POST" action="{{ route('sales.update', $sale) }}">
            @csrf
            @method('PUT')

            @include('sales._form')

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
                >
                    Simpan
                </button>
                <a href="{{ route('sales.index') }}" class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
