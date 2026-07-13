<header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-gray-300 bg-white px-4 dark:border-gray-700 dark:bg-gray-800 sm:px-6 lg:px-8">
    <div class="flex items-center gap-4">
        <button
            @click="sidebarOpen = !sidebarOpen"
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10 lg:hidden"
        >
            <span class="sr-only">Buka menu</span>
            <x-icon name="bars-3" size="6" />
        </button>

        <div class="hidden items-center rounded-lg bg-gray-100 px-3 py-2.5 dark:bg-gray-700 sm:flex">
            <x-icon name="magnifying-glass" size="4" class="text-gray-500 dark:text-gray-400" />
            <input
                type="text"
                placeholder="Cari..."
                class="ml-2 w-48 border-0 bg-transparent p-0 text-sm font-medium text-gray-700 placeholder:text-gray-400 placeholder:font-normal focus:outline-none focus:ring-0 dark:text-gray-200 dark:placeholder:text-gray-500"
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
            <button @click="open = !open" type="button" class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10">
                <span class="sr-only">Notifikasi</span>
                <x-icon name="bell" size="6" />
                @if($unreadNotificationsCount > 0)
                    <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-danger ring-2 ring-white dark:ring-gray-800"></span>
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
                            <button type="submit" class="inline-flex h-6 w-6 items-center justify-center rounded text-gray-400 hover:bg-gray-200 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-white/10 dark:hover:text-gray-300" title="Tandai dibaca">
                                <x-icon name="x-mark" size="4" />
                            </button>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">Tidak ada notifikasi baru.</p>
                @endforelse
            </div>
        </div>

        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open" type="button" class="flex items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-gray-100 dark:hover:bg-white/10">
                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white shadow-sm shadow-primary/30">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                </span>
                <span class="hidden text-sm font-semibold text-gray-700 dark:text-gray-200 sm:inline">{{ auth()->user()?->name ?? 'User' }}</span>
                <x-icon name="chevron-down" size="4" class="hidden text-gray-400 sm:inline" />
            </button>

            <div
                x-show="open"
                @click.outside="open = false"
                x-transition
                class="absolute right-0 z-30 mt-2 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
                style="display: none;"
            >
                <a href="#" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">
                    <x-icon name="user-circle" size="5" class="text-gray-400" />
                    Profil
                </a>
                @can('settings.view')
                    <a href="{{ route('settings.index') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">
                        <x-icon name="cog-6-tooth" size="5" class="text-gray-400" />
                        Pengaturan
                    </a>
                @endcan
                <div class="my-1 border-t border-gray-200 dark:border-gray-700"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-medium text-danger hover:bg-danger-light dark:hover:bg-danger/10">
                        <x-icon name="arrow-right-on-rectangle" size="5" />
                        Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
