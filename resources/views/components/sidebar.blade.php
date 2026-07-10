@php
    // TODO: hanya "Dashboard", "Pengguna", "Produk", "Paket", "PoP",
    // "Coverage", "Layanan", dan "Penjualan" yang mengarah ke route
    // sungguhan; sisanya placeholder sampai modul terkait dibangun (lihat
    // roadmap modul di README.md).
    $menu = [
        ['label' => 'Dashboard', 'route' => 'dashboard'],
        ['label' => 'Pengguna', 'route' => 'users.index'],
        ['label' => 'Produk', 'route' => 'products.index'],
        ['label' => 'Paket', 'route' => 'packages.index'],
        ['label' => 'PoP', 'route' => 'pops.index'],
        ['label' => 'Coverage', 'route' => 'coverages.index'],
        ['label' => 'Layanan', 'route' => 'services.index'],
        ['label' => 'Penjualan', 'route' => 'sales.index'],
        ['label' => 'Billing'],
        ['label' => 'Tiket'],
        ['label' => 'Inventaris'],
        ['label' => 'Vendor & Supplier'],
        ['label' => 'Laporan'],
        ['label' => 'Pengaturan'],
    ];
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
            <span class="text-lg font-semibold text-white">{{ config('app.name', 'NEXA') }}</span>
        </a>
    </div>

    <nav class="h-[calc(100%-4rem)] overflow-y-auto px-3 py-4">
        <ul class="space-y-1">
            @foreach ($menu as $item)
                @php $active = isset($item['route']) && request()->routeIs($item['route']); @endphp
                <li>
                    <a
                        href="{{ isset($item['route']) ? route($item['route']) : '#' }}"
                        @class([
                            'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                            'bg-primary text-white' => $active,
                            'text-gray-400 hover:bg-white/5 hover:text-white' => ! $active,
                        ])
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current opacity-60"></span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
</aside>
