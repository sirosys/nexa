{{-- Filter tanggal, dipakai sama di keempat halaman laporan. $from/$to
    selalu sudah terisi (default awal bulan berjalan s/d hari ini kalau
    query string kosong — lihat ReportController::resolveRange()). --}}
<form method="GET" action="{{ route(request()->route()->getName()) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
    <div>
        <label for="from" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400">Dari Tanggal</label>
        <input
            type="date"
            name="from"
            id="from"
            value="{{ $from->format('Y-m-d') }}"
            class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:bg-gray-800 dark:text-white"
        >
    </div>

    <div>
        <label for="to" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-400">Sampai Tanggal</label>
        <input
            type="date"
            name="to"
            id="to"
            value="{{ $to->format('Y-m-d') }}"
            class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:bg-gray-800 dark:text-white"
        >
    </div>

    <button
        type="submit"
        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
    >
        <x-icon name="magnifying-glass" size="4" />
        Terapkan
    </button>

    <a href="{{ route(request()->route()->getName()) }}" class="pb-2.5 text-sm font-semibold text-gray-500 hover:text-primary dark:text-gray-400">
        Reset
    </a>
</form>
