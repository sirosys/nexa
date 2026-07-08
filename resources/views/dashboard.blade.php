@php
    // Placeholder — angka dummy, belum terhubung ke data nyata.
    // Kelas badge ditulis literal (bukan interpolasi) supaya terdeteksi Tailwind saat build.
    $stats = [
        ['label' => 'Pelanggan Aktif', 'value' => '—', 'badge' => 'bg-primary-light text-primary dark:bg-primary/10'],
        ['label' => 'Tagihan Belum Lunas', 'value' => '—', 'badge' => 'bg-warning-light text-warning dark:bg-warning/10'],
        ['label' => 'Pendapatan Bulan Ini', 'value' => '—', 'badge' => 'bg-success-light text-success dark:bg-success/10'],
        ['label' => 'Tiket Terbuka', 'value' => '—', 'badge' => 'bg-danger-light text-danger dark:bg-danger/10'],
    ];
@endphp

<x-app-layout :title="'Dashboard — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Ringkasan operasional NEXA. Halaman ini masih placeholder.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-2xl border border-gray-300 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <span class="inline-flex items-center rounded-full {{ $stat['badge'] }} px-2.5 py-1 text-xs font-medium">
                    {{ $stat['label'] }}
                </span>
                <p class="mt-4 text-2xl font-semibold text-gray-900 dark:text-white">{{ $stat['value'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-gray-300 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Modul mendatang</h2>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            Widget, grafik, dan tabel data nyata akan menyusul setelah modul Pelanggan, Billing, dan lainnya dibangun.
        </p>
    </div>
</x-app-layout>
