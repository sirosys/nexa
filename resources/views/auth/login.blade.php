<x-auth-layout :title="'Masuk — ' . config('app.name', 'NEXA')">
    <div class="mb-8 text-center">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Masuk ke NEXA</h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Masukkan nomor telepon Anda untuk menerima kode OTP melalui WhatsApp.</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-danger/20 bg-danger-light px-4 py-3 text-sm text-danger dark:border-danger/30 dark:bg-danger/10">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.send') }}" class="space-y-4">
        @csrf

        <div>
            <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nomor Telepon</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                inputmode="numeric"
                autocomplete="tel"
                placeholder="08xxxxxxxxxx"
                value="{{ old('phone') }}"
                required
                class="block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-500"
            >
        </div>

        <button
            type="submit"
            class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
        >
            Kirim OTP
        </button>
    </form>
</x-auth-layout>
