<x-app-layout :title="'Ubah Service — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Ubah Service</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kode: {{ $service->code }}</p>
    </div>

    <div class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <form method="POST" action="{{ route('services.update', $service) }}">
            @csrf
            @method('PUT')

            @include('services._form')

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
                >
                    Simpan
                </button>
                <a href="{{ route('services.index') }}" class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
