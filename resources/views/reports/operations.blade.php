@php
    use App\Models\ServiceTicket;

    // Kelas badge/aksen ditulis literal (bukan interpolasi) supaya terdeteksi
    // content-scanner Tailwind v4 saat build — pola sama dashboard.blade.php.
    $snapshotCards = [
        ['label' => 'Antrean Instalasi', 'value' => number_format($snapshot['installation_queue']), 'badge' => 'bg-primary-light text-primary dark:bg-primary/10', 'accent' => 'bg-primary', 'icon' => 'wrench-screwdriver'],
        ['label' => 'Antrean Dismantle', 'value' => number_format($snapshot['dismantle_queue']), 'badge' => 'bg-danger-light text-danger dark:bg-danger/10', 'accent' => 'bg-danger', 'icon' => 'bolt-slash'],
        ['label' => 'Tiket Terbuka', 'value' => number_format($snapshot['open_tickets']), 'badge' => 'bg-warning-light text-warning dark:bg-warning/10', 'accent' => 'bg-warning', 'icon' => 'ticket'],
    ];

    $summaryCards = [
        ['label' => 'Instalasi Selesai', 'value' => number_format($summary['installations_completed']), 'badge' => 'bg-success-light text-success dark:bg-success/10', 'accent' => 'bg-success', 'icon' => 'wrench-screwdriver'],
        ['label' => 'Dismantle Selesai', 'value' => number_format($summary['dismantles_completed']), 'badge' => 'bg-success-light text-success dark:bg-success/10', 'accent' => 'bg-success', 'icon' => 'bolt-slash'],
        ['label' => 'Tiket Terselesaikan', 'value' => number_format($summary['tickets_resolved']), 'badge' => 'bg-success-light text-success dark:bg-success/10', 'accent' => 'bg-success', 'icon' => 'ticket'],
        ['label' => 'Rata-rata Penyelesaian Tiket', 'value' => number_format($summary['avg_ticket_resolution_hours'], 1).' jam', 'badge' => 'bg-info-light text-info dark:bg-info/10', 'accent' => 'bg-info', 'icon' => 'clock'],
    ];
@endphp

<x-app-layout :title="'Laporan Operasional — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Laporan</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Antrean lapangan saat ini &amp; pekerjaan yang selesai pada periode terpilih.</p>
    </div>

    @include('reports._nav')

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        @foreach ($snapshotCards as $card)
            <div class="relative overflow-hidden rounded-2xl border border-gray-300 bg-white p-5 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                <span class="absolute inset-x-0 top-0 h-1 {{ $card['accent'] }}"></span>
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $card['badge'] }}">
                    <x-icon :name="$card['icon']" size="6" />
                </span>
                <p class="mt-4 text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ $card['value'] }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $card['label'] }} (sekarang)</p>
            </div>
        @endforeach
    </div>

    @include('reports._filter')

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($summaryCards as $card)
            <div class="relative overflow-hidden rounded-2xl border border-gray-300 bg-white p-5 shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
                <span class="absolute inset-x-0 top-0 h-1 {{ $card['accent'] }}"></span>
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $card['badge'] }}">
                    <x-icon :name="$card['icon']" size="6" />
                </span>
                <p class="mt-4 text-2xl font-black tracking-tight text-gray-900 dark:text-white">{{ $card['value'] }}</p>
                <p class="mt-1 truncate text-sm font-semibold text-gray-500 dark:text-gray-400">{{ $card['label'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Instalasi Selesai</h2>
        </div>

        @if ($installations->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada instalasi selesai pada periode ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-6 py-3">Layanan</th>
                            <th class="px-4 py-3">Teknisi</th>
                            <th class="px-4 py-3">Diklaim</th>
                            <th class="px-4 py-3">Selesai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($installations as $activation)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3">
                                    <a href="{{ route('installations.show', $activation->service) }}" class="font-semibold text-primary hover:underline">{{ $activation->service?->code ?? '—' }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $activation->installer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $activation->claimed_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $activation->completed_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($installations->hasPages())
                <div class="border-t border-gray-300 p-4 dark:border-gray-700">{{ $installations->links() }}</div>
            @endif
        @endif
    </div>

    <div class="mt-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Dismantle Selesai</h2>
        </div>

        @if ($dismantles->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada dismantle selesai pada periode ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-6 py-3">Layanan</th>
                            <th class="px-4 py-3">Teknisi</th>
                            <th class="px-4 py-3">Sumber Antrean</th>
                            <th class="px-4 py-3">Diklaim</th>
                            <th class="px-4 py-3">Selesai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($dismantles as $dismantle)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3">
                                    <a href="{{ route('dismantles.show', $dismantle->service) }}" class="font-semibold text-primary hover:underline">{{ $dismantle->service?->code ?? '—' }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $dismantle->technician?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $dismantle->queued_by === null ? 'Otomatis' : 'Manual' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $dismantle->claimed_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $dismantle->completed_at?->translatedFormat('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($dismantles->hasPages())
                <div class="border-t border-gray-300 p-4 dark:border-gray-700">{{ $dismantles->links() }}</div>
            @endif
        @endif
    </div>

    <div class="mt-6 rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <h2 class="text-base font-bold text-gray-900 dark:text-white">Tiket Diselesaikan</h2>
        </div>

        @if ($tickets->isEmpty())
            <p class="px-6 py-6 text-sm text-gray-500 dark:text-gray-400">Tidak ada tiket terselesaikan pada periode ini.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <th class="px-6 py-3">Kode</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Teknisi</th>
                            <th class="px-4 py-3">Dibuat</th>
                            <th class="px-4 py-3">Selesai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($tickets as $ticket)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3">
                                    <a href="{{ route('tickets.show', $ticket) }}" class="font-semibold text-primary hover:underline">{{ $ticket->code }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ ServiceTicket::CATEGORY_LABELS[$ticket->category] ?? $ticket->category }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $ticket->assignedTechnician?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $ticket->created_at?->translatedFormat('d M Y H:i') }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $ticket->solved_at?->translatedFormat('d M Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($tickets->hasPages())
                <div class="border-t border-gray-300 p-4 dark:border-gray-700">{{ $tickets->links() }}</div>
            @endif
        @endif
    </div>
</x-app-layout>
