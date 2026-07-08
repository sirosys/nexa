<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <x-theme-init />
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $title ?? config('app.name', 'NEXA') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-100 font-sans antialiased dark:bg-gray-900">
        <div x-data="{ sidebarOpen: false }" class="min-h-screen">
            <x-sidebar />

            <div class="flex min-h-screen flex-col lg:pl-64">
                <x-header />

                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>

                <x-footer />
            </div>
        </div>
    </body>
</html>
