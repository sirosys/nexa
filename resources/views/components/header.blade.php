<header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-gray-300 bg-white px-4 dark:border-gray-700 dark:bg-gray-800 sm:px-6 lg:px-8">
    <div class="flex items-center gap-4">
        <button
            @click="sidebarOpen = !sidebarOpen"
            type="button"
            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10 lg:hidden"
        >
            <span class="sr-only">Buka menu</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
            </svg>
        </button>

        <div class="hidden items-center rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-700 sm:flex">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
            <input
                type="text"
                placeholder="Cari..."
                class="ml-2 w-48 border-0 bg-transparent p-0 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:text-gray-200 dark:placeholder:text-gray-500"
            >
        </div>
    </div>

    <div class="flex items-center gap-3">
        <x-theme-toggle />

        @php
            $unreadNotifications = auth()->user()?->unreadNotifications->take(10) ?? collect();
            $unreadNotificationsCount = auth()->user()?->unreadNotifications->count() ?? 0;
        @endphp
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" type="button" class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">
                <span class="sr-only">Notifikasi</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.85 23.85 0 0 0 5.454-1.31A8.97 8.97 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.26 24.26 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                </svg>
                @if($unreadNotificationsCount > 0)
                    <span class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-danger"></span>
                @endif
            </button>

            <div
                x-show="open"
                @click.outside="open = false"
                x-transition
                class="absolute right-0 z-30 mt-2 w-80 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                style="display: none;"
            >
                <div class="flex items-center justify-between px-4 py-2">
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Notifikasi</span>
                    @if($unreadNotificationsCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button type="submit" class="text-xs font-medium text-primary hover:underline">Tandai semua dibaca</button>
                        </form>
                    @endif
                </div>
                <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>

                @forelse($unreadNotifications as $notification)
                    <div class="flex items-start gap-2 px-4 py-2 hover:bg-gray-100 dark:hover:bg-white/5">
                        <div class="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full bg-primary"></div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-gray-700 dark:text-gray-200">{{ $notification->data['title'] ?? 'Notifikasi' }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $notification->data['message'] ?? '' }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="text-xs text-gray-400 hover:text-primary dark:text-gray-500" title="Tandai dibaca">&times;</button>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Tidak ada notifikasi baru.</p>
                @endforelse
            </div>
        </div>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" type="button" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                </span>
                <span class="hidden text-sm font-medium text-gray-700 dark:text-gray-200 sm:inline">{{ auth()->user()?->name ?? 'User' }}</span>
            </button>

            <div
                x-show="open"
                @click.outside="open = false"
                x-transition
                class="absolute right-0 z-30 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                style="display: none;"
            >
                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">Profil</a>
                <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">Pengaturan</a>
                <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">Keluar</button>
                </form>
            </div>
        </div>
    </div>
</header>
