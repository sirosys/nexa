@php
    $statusBadges = \App\Models\ServiceTicket::STATUS_LABELS;
    $statusClasses = [
        'open' => 'bg-warning-light text-warning dark:bg-warning/10',
        'in_progress' => 'bg-info-light text-info dark:bg-info/10',
        'resolved' => 'bg-success-light text-success dark:bg-success/10',
    ];
@endphp

<x-app-layout :title="'Tiket — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Tiket</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Keluhan dan permintaan pelanggan terkait sebuah layanan.</p>
        </div>

        <a
            href="{{ route('tickets.create') }}"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
        >
        <x-icon name="plus" size="4" />
        Tambah Tiket
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('tickets.index') }}" class="flex flex-wrap items-center gap-3">
                <input
                    type="text"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Cari kode atau subjek..."
                    class="w-full max-w-sm rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
                <select name="status" onchange="this.form.submit()" class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">
                    <option value="">Semua Status</option>
                    @foreach ($statusBadges as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="category" onchange="this.form.submit()" class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">
                    <option value="">Semua Kategori</option>
                    @foreach (\App\Models\ServiceTicket::CATEGORY_LABELS as $value => $label)
                        <option value="{{ $value }}" @selected($category === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">Cari</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Subjek</th>
                        <th class="px-4 py-3">Service</th>
                        <th class="px-4 py-3">Kategori</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($tickets as $ticket)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $ticket->code }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $ticket->subject }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 dark:text-white">{{ $ticket->service?->code }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $ticket->service?->user?->name }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ \App\Models\ServiceTicket::CATEGORY_LABELS[$ticket->category] ?? $ticket->category }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full {{ $statusClasses[$ticket->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">
                                    {{ $statusBadges[$ticket->status] ?? $ticket->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <x-row-action :href="route('tickets.show', $ticket)" icon="eye" label="Detail" />
                                    @can('update', $ticket)
                                        <x-row-action :href="route('tickets.edit', $ticket)" icon="pencil-square" label="Ubah" variant="primary" />
                                    @endcan
                                    @can('delete', $ticket)
                                        <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Hapus tiket ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <x-row-action icon="trash" label="Hapus" variant="danger" />
                                    </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada tiket.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($tickets->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
