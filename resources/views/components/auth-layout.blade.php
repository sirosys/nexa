<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <x-theme-init />
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'NEXA') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-100 font-sans antialiased dark:bg-gray-900">
        <div class="absolute right-4 top-4">
            <x-theme-toggle />
        </div>

        <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <a href="{{ url('/') }}" class="mb-8">
                {{-- logo-white-bg (X hitam) untuk light mode, logo-black-bg (X putih) untuk dark mode --}}
                <img
                    src="{{ asset('images/logo/logo-white-bg.png') }}"
                    alt="{{ config('app.name', 'NEXA') }}"
                    class="h-14 w-14 rounded-xl dark:hidden"
                >
                <img
                    src="{{ asset('images/logo/logo-black-bg.png') }}"
                    alt="{{ config('app.name', 'NEXA') }}"
                    class="hidden h-14 w-14 rounded-xl dark:block"
                >
            </a>

            <div class="w-full max-w-md rounded-2xl border border-gray-300 bg-white p-8 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                {{ $slot }}
            </div>

            <p class="mt-8 text-sm text-gray-500 dark:text-gray-400">
                &copy; {{ date('Y') }} XPlus Network Indonesia. Seluruh hak cipta dilindungi.
            </p>
        </div>
    </body>
</html>
