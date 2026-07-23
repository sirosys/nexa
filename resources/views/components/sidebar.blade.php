@php
    // Setiap item wajib punya 'permission' (atau null kalau memang harus selalu
    // tampil untuk siapa pun yang bisa login, mis. Dashboard) supaya role yang
    // tidak berwenang (technician/finance) tidak melihat menu yang kalau
    // diklik cuma akan 403 — permission sama persis dengan yang dipakai Policy
    // masing-masing modul (lihat CLAUDE.md "Authorization / Role & Permission").
    // Item yang route-nya salah satu dari beberapa sub-halaman (mis. "Laporan",
    // 4 kategori) pakai 'active' terpisah (route pattern wildcard) supaya
    // menu tetap ter-highlight di semua sub-halamannya, bukan cuma yang
    // persis sama dengan 'route'. Tidak ada lagi item placeholder tanpa
    // 'route' (mis. "Billing" yang dihapus 2026-07-17 — tidak pernah py
    // halaman admin sendiri, status/link pembayaran cukup di
    // service-orders.show).
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
                ['label' => 'Order Layanan', 'route' => 'service-orders.index', 'icon' => 'shopping-cart', 'permission' => 'service_orders.view'],
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
                ['label' => 'Site', 'route' => 'sites.index', 'icon' => 'server', 'permission' => 'sites.view'],
                ['label' => 'Coverage', 'route' => 'coverages.index', 'icon' => 'map', 'permission' => 'coverages.view'],
            ],
        ],
        [
            'label' => 'Katalog & Gudang',
            'items' => [
                ['label' => 'Plan', 'route' => 'plans.index', 'icon' => 'signal', 'permission' => 'plans.view'],
                ['label' => 'Produk', 'route' => 'products.index', 'icon' => 'cube', 'permission' => 'products.view'],
                ['label' => 'Paket', 'route' => 'packages.index', 'icon' => 'gift', 'permission' => 'packages.view'],
            ],
        ],
        [
            'label' => 'Lainnya',
            'items' => [
                ['label' => 'Laporan', 'route' => 'reports.finance', 'active' => 'reports.*', 'icon' => 'chart-bar', 'permission' => 'reports.view'],
            ],
        ],
        [
            'label' => 'Sistem',
            'items' => [
                ['label' => 'Pengaturan', 'route' => 'settings.index', 'icon' => 'cog-6-tooth', 'permission' => 'settings.view'],
                ['label' => 'Role & Permission', 'route' => 'roles.index', 'active' => 'roles.*', 'icon' => 'identification', 'permission' => 'roles.view'],
                ['label' => 'Log Aktivitas', 'route' => 'audit-logs.index', 'icon' => 'shield-check', 'permission' => 'audit_logs.view'],
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

{{-- Overlay dim di belakang aside off-canvas — rgba(0,0,0,0.1) meniru persis
     `.offcanvas-overlay` Metronic v7.0.0 (jauh lebih tipis dari overlay modal
     Tailwind pada umumnya yang biasanya 50%), durasi fade 300ms meniru
     `animation-offcanvas-fade-in .6s`/transisi aside 0.3s di CSS aslinya.
     `lg:hidden` WAJIB ada di sini (bukan cuma di aside) — mencegah overlay
     ikut tampil di desktop kalau `sidebarOpen` kebetulan masih `true` dari
     sesi mobile sebelumnya (mis. resize window tanpa reload). --}}
<div
    x-show="sidebarOpen"
    x-transition.opacity.duration.300ms
    @click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-black/10 lg:hidden"
    style="display: none;"
></div>

<aside
    class="fixed inset-y-0 left-0 z-40 w-[265px] transform bg-aside transition-transform duration-300 ease-in-out lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
>
    {{-- "Brand" area — warna sedikit lebih gelap dari badan sidebar (bg-aside),
         meniru pemisahan visual brand/aside-menu ala Metronic v7.0.0 dark aside
         (lihat CLAUDE.md "Referensi Desain UI"). --}}
    <div class="flex h-16 items-center justify-center gap-2 bg-brand px-4">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
            {{-- Sidebar selalu gelap terlepas dari mode terang/gelap app, jadi selalu pakai varian logo-black-bg (X putih) --}}
            <img src="{{ asset('images/logo/logo-black-bg.png') }}" alt="{{ config('app.name', 'NEXA') }}" class="h-9 w-9 rounded-lg">
            <span class="text-lg font-bold tracking-tight text-white">{{ config('app.name', 'NEXA') }}</span>
        </a>
    </div>

    <nav class="h-[calc(100%-4rem)] overflow-y-auto px-3 py-4">
        @foreach ($visibleGroups as $group)
            @if ($group['label'])
                <p class="mb-2 mt-5 px-3 text-[11px] font-bold uppercase tracking-wider text-aside-section first:mt-0">
                    {{ $group['label'] }}
                </p>
            @endif
            <ul class="space-y-1">
                @foreach ($group['items'] as $item)
                    @php $active = isset($item['route']) && request()->routeIs($item['active'] ?? $item['route']); @endphp
                    <li>
                        <a
                            href="{{ isset($item['route']) ? route($item['route']) : '#' }}"
                            @class([
                                'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold transition',
                                'bg-aside-active text-white' => $active,
                                'text-aside-muted hover:bg-aside-active hover:text-white' => ! $active,
                            ])
                        >
                            <x-icon
                                :name="$item['icon']"
                                size="5"
                                :class="$active ? 'text-primary' : 'text-aside-icon transition group-hover:text-primary'"
                            />
                            <span>{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>
</aside>
