<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <x-theme-init />
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'NEXA') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white font-sans antialiased dark:bg-gray-900">
        <header class="relative z-10 flex items-center justify-between bg-gray-900 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ url('/') }}" class="flex items-center gap-2">
                {{-- Nav bar selalu gelap (bg-gray-900) terlepas dari mode terang/gelap app, jadi selalu pakai varian logo-black-bg (X putih) --}}
                <img src="{{ asset('images/logo/logo-black-bg.png') }}" alt="{{ config('app.name', 'NEXA') }}" class="h-9 w-9 rounded-lg">
                <span class="text-lg font-semibold text-white">{{ config('app.name', 'NEXA') }}</span>
            </a>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    x-data="{ dark: document.documentElement.classList.contains('dark') }"
                    @click="
                        dark = !dark;
                        document.documentElement.classList.toggle('dark', dark);
                        localStorage.setItem('theme', dark ? 'dark' : 'light');
                    "
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-300 hover:bg-white/10"
                >
                    <span class="sr-only">Ganti tema terang/gelap</span>
                    <svg x-show="!dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                    </svg>
                    <svg x-show="dark" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="display: none;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                    </svg>
                </button>

                <a href="{{ route('login') }}" class="rounded-lg border border-white/30 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/10">
                    Masuk
                </a>
            </div>
        </header>

        {{-- Hero --}}
        <section class="relative isolate flex min-h-[calc(100vh-4rem)] items-center justify-center overflow-hidden bg-gray-900">
            <div class="absolute -top-40 left-1/2 h-[500px] w-[900px] -translate-x-1/2 rounded-full bg-info/30 blur-3xl"></div>

            <div class="relative z-10 mx-auto max-w-3xl px-6 text-center">
                <h1 class="text-4xl font-bold text-white sm:text-6xl">Internet Cerdas</h1>
                <p class="mt-4 text-lg text-gray-300 sm:text-2xl">Beralih ke yang lebih cerdas.</p>
                <div class="mt-8">
                    {{-- TODO: belum ada alur pendaftaran mandiri pelanggan baru, arahkan sementara ke halaman masuk --}}
                    <a
                        href="{{ route('login') }}"
                        class="inline-block rounded-lg bg-primary px-6 py-3 text-sm font-semibold text-white transition hover:bg-primary-active"
                    >
                        Daftar Sekarang
                    </a>
                </div>
            </div>
        </section>

        {{-- Ringkasan layanan --}}
        <section class="bg-white py-20 dark:bg-gray-900">
            <div class="mx-auto max-w-6xl px-6">
                <div class="mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white">Layanan XNet</h2>
                    <p class="mt-3 text-gray-500 dark:text-gray-400">
                        Internet rumah dan bisnis dengan jaringan fiber, ditunjang layanan pelanggan dan billing yang transparan.
                    </p>
                </div>

                <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Jaringan Fiber</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Koneksi stabil berbasis fiber optik hingga ke rumah pelanggan.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Billing Transparan</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Tagihan dan riwayat pembayaran yang jelas, terintegrasi dengan Xendit.
                        </p>
                    </div>
                    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">Dukungan Pelanggan</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Tim dukungan siap membantu lewat tiket layanan.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <footer class="border-t border-gray-200 bg-white px-4 py-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
            &copy; {{ date('Y') }} XPlus Network Indonesia &mdash; NEXA. Seluruh hak cipta dilindungi.
        </footer>
    </body>
</html>
