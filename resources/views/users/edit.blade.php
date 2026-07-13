<x-app-layout :title="'Ubah Pengguna — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Ubah Pengguna</h1>
        @if ($user->code)
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kode: {{ $user->code }}</p>
        @endif
    </div>

    <div class="max-w-xl rounded-2xl border border-gray-300 bg-white p-6 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <form method="POST" action="{{ route('users.update', $user) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            @include('users._form')

            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
                >
                    Simpan
                </button>
                <a href="{{ route('users.index') }}" class="text-sm font-medium text-gray-600 hover:underline dark:text-gray-300">Batal</a>
            </div>
        </form>
    </div>
</x-app-layout>
