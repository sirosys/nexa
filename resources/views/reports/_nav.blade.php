@php
    $reportTabs = [
        ['route' => 'reports.finance', 'label' => 'Keuangan & Billing'],
        ['route' => 'reports.operations', 'label' => 'Operasional Lapangan'],
        ['route' => 'reports.customers', 'label' => 'Pelanggan & Layanan'],
    ];
@endphp

<div class="mb-6 flex flex-wrap gap-1 border-b border-gray-300 dark:border-gray-700">
    @foreach ($reportTabs as $tab)
        <a
            href="{{ route($tab['route']) }}"
            @class([
                'border-b-2 px-4 py-2.5 text-sm font-semibold transition',
                'border-primary text-primary' => request()->routeIs($tab['route']),
                'border-transparent text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' => ! request()->routeIs($tab['route']),
            ])
        >
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
