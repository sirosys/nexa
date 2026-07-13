@php
    // Setiap item wajib punya 'permission' (atau null kalau memang harus selalu
    // tampil untuk siapa pun yang bisa login, mis. Dashboard) supaya role yang
    // tidak berwenang (technician/finance/sales) tidak melihat menu yang kalau
    // diklik cuma akan 403 — permission sama persis dengan yang dipakai Policy
    // masing-masing modul (lihat CLAUDE.md "Authorization / Role & Permission").
    // Item TANPA 'route' (placeholder, mis. "Billing"/"Laporan") sengaja tidak
    // diberi permission — modulnya belum ada, jadi belum ada yang bisa digate.
    //
    // Dikelompokkan per area kerja (bukan lagi daftar flat 17 item) supaya lebih
    // gampang dipindai — meniru pengelompokan menu ala Metronic. Label grup
    // "null" (Dashboard) sengaja tidak dapat header section.
    $groups = [
        [
            'label' => null,
            'items' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'permission' => null],
            ],
        ],
        [
            'label' => 'Pelanggan & Layanan',
            'items' => [
                ['label' => 'Pengguna', 'route' => 'users.index', 'icon' => 'users', 'permission' => 'users.view'],
                ['label' => 'Layanan', 'route' => 'services.index', 'icon' => 'wifi', 'permission' => 'services.view'],
                ['label' => 'Penjualan', 'route' => 'sales.index', 'icon' => 'shopping-cart', 'permission' => 'sales.view'],
                ['label' => 'Billing', 'icon' => 'credit-card', 'permission' => null],
                ['label' => 'Tiket', 'route' => 'tickets.index', 'icon' => 'ticket', 'permission' => 'tickets.view'],
            ],
        ],
        [
            'label' => 'Operasional Lapangan',
            'items' => [
                ['label' => 'Instalasi', 'route' => 'installations.index', 'icon' => 'wrench-screwdriver', 'permission' => 'installations.view'],
                ['label' => 'Dismantle', 'route' => 'dismantles.index', 'icon' => 'bolt-slash', 'permission' => 'dismantles.view'],
            ],
        ],
        [
            'label' => 'Jaringan',
            'items' => [
                ['label' => 'PoP', 'route' => 'pops.index', 'icon' => 'server', 'permission' => 'pops.view'],
                ['label' => 'Coverage', 'route' => 'coverages.index', 'icon' => 'map', 'permission' => 'coverages.view'],
            ],
        ],
        [
            'label' => 'Katalog & Gudang',
            'items' => [
                ['label' => 'Produk', 'route' => 'products.index', 'icon' => 'cube', 'permission' => 'products.view'],
                ['label' => 'Paket', 'route' => 'packages.index', 'icon' => 'gift', 'permission' => 'packages.view'],
                ['label' => 'Inventaris', 'route' => 'inventory-items.index', 'icon' => 'archive-box', 'permission' => 'inventory.view'],
                ['label' => 'Vendor', 'route' => 'vendors.index', 'icon' => 'truck', 'permission' => 'vendors.view'],
                ['label' => 'Purchase Order', 'route' => 'purchase-orders.index', 'icon' => 'clipboard-document-list', 'permission' => 'purchase_orders.view'],
            ],
        ],
        [
            'label' => 'Lainnya',
            'items' => [
                ['label' => 'Laporan', 'icon' => 'chart-bar', 'permission' => null],
            ],
        ],
        [
            'label' => 'Sistem',
            'items' => [
                ['label' => 'Pengaturan', 'route' => 'settings.index', 'icon' => 'cog-6-tooth', 'permission' => 'settings.view'],
            ],
        ],
    ];

    $visibleGroups = collect($groups)
        ->map(function ($group) {
            $group['items'] = collect($group['items'])
                ->filter(fn ($item) => $item['permission'] === null || auth()->user()?->can($item['permission']))
                ->values();

            return $group;
        })
        ->filter(fn ($group) => $group['items']->isNotEmpty());
@endphp

<div
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-gray-900/50 lg:hidden"
    style="display: none;"
></div>

<aside
    class="fixed inset-y-0 left-0 z-40 w-64 transform bg-gray-900 transition-transform duration-200 ease-in-out lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    <div class="flex h-16 items-center justify-center gap-2 border-b border-white/10 px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            {{-- Sidebar selalu gelap terlepas dari mode terang/gelap app, jadi selalu pakai varian logo-black-bg (X putih) --}}
            <img src="{{ asset('images/logo/logo-black-bg.png') }}" alt="{{ config('app.name', 'NEXA') }}" class="h-9 w-9 rounded-lg">
            <span class="text-lg font-bold tracking-tight text-white">{{ config('app.name', 'NEXA') }}</span>
        </a>
    </div>

    <nav class="h-[calc(100%-4rem)] overflow-y-auto px-3 py-4">
        @foreach ($visibleGroups as $group)
            @if ($group['label'])
                <p class="mb-2 mt-5 px-3 text-[11px] font-bold uppercase tracking-wider text-gray-500 first:mt-0">
                    {{ $group['label'] }}
                </p>
            @endif
            <ul class="space-y-1">
                @foreach ($group['items'] as $item)
                    @php $active = isset($item['route']) && request()->routeIs($item['route']); @endphp
                    <li>
                        <a
                            href="{{ isset($item['route']) ? route($item['route']) : '#' }}"
                            @class([
                                'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition',
                                'bg-primary text-white shadow-sm shadow-primary/30' => $active,
                                'text-gray-400 hover:bg-white/5 hover:text-white' => ! $active,
                            ])
                        >
                            <x-icon
                                :name="$item['icon']"
                                size="5"
                                :class="$active ? 'text-white' : 'text-gray-500 transition group-hover:text-white'"
                            />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>
</aside>
