@php
    $statusBadges = \App\Models\ServiceTicket::STATUS_LABELS;
    $statusClasses = [
        'open' => 'bg-warning-light text-warning dark:bg-warning/10',
        'in_progress' => 'bg-info-light text-info dark:bg-info/10',
        'resolved' => 'bg-success-light text-success dark:bg-success/10',
    ];
    $requiresTechnician = in_array($ticket->category, \App\Models\ServiceTicket::CATEGORIES_REQUIRING_TECHNICIAN, true);
    $canBeResolvedNow = $requiresTechnician
        ? $ticket->status === \App\Models\ServiceTicket::STATUS_IN_PROGRESS
        : $ticket->status === \App\Models\ServiceTicket::STATUS_OPEN;
@endphp

<x-app-layout :title="'Detail Tiket — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Tiket</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $ticket->code }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $ticket->subject }}</p>
        </div>

        @can('update', $ticket)
            <a
                href="{{ route('tickets.edit', $ticket) }}"
                class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
            >
                Ubah
            </a>
        @endcan
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 rounded-lg border border-danger/20 bg-danger-light px-4 py-3 text-sm text-danger dark:border-danger/30 dark:bg-danger/10">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2">
            <dl>
                <x-detail-row label="Status">
                    <span class="inline-flex items-center rounded-full {{ $statusClasses[$ticket->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-2.5 py-1 text-xs font-medium">
                        {{ $statusBadges[$ticket->status] ?? $ticket->status }}
                    </span>
                </x-detail-row>
                <x-detail-row label="Kategori">{{ \App\Models\ServiceTicket::CATEGORY_LABELS[$ticket->category] ?? $ticket->category }}</x-detail-row>
                <x-detail-row label="Service">
                    @if ($ticket->service)
                        <a href="{{ route('services.show', $ticket->service) }}" class="font-medium text-primary hover:underline">{{ $ticket->service->code }}</a>
                    @else
                        —
                    @endif
                </x-detail-row>
                <x-detail-row label="Pelanggan">
                    @if ($ticket->service?->user)
                        <a href="{{ route('users.show', $ticket->service->user) }}" class="font-medium text-primary hover:underline">{{ $ticket->service->user->name }}</a>
                        <span class="text-gray-500 dark:text-gray-400">({{ $ticket->service->user->phone }})</span>
                    @else
                        —
                    @endif
                </x-detail-row>
                <x-detail-row label="Deskripsi">{{ $ticket->description }}</x-detail-row>
                <x-detail-row label="Teknisi">{{ $ticket->assignedTechnician?->name ?? ($requiresTechnician ? 'Belum ditugaskan' : '—') }}</x-detail-row>
                <x-detail-row label="Ditugaskan Oleh">{{ $ticket->assignedBy?->name ?? ($ticket->assignedTechnician ? 'Klaim sendiri' : '—') }}</x-detail-row>
                <x-detail-row label="Diklaim/Ditugaskan Pada">{{ $ticket->claimed_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
                <x-detail-row label="Catatan Penyelesaian">{{ $ticket->resolution_notes ?? '—' }}</x-detail-row>
                <x-detail-row label="Diselesaikan Oleh">{{ $ticket->solvedBy?->name ?? '—' }}</x-detail-row>
                <x-detail-row label="Selesai Pada">{{ $ticket->solved_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
                <x-detail-row label="Dibuat Pada">{{ $ticket->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
            </dl>
        </div>

        <div class="space-y-6">
            @if ($requiresTechnician && $ticket->status === \App\Models\ServiceTicket::STATUS_OPEN && $ticket->assigned_technician_id === null)
                @can('assignTicket', $ticket)
                    <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Tugaskan Teknisi</h2>
                        <form method="POST" action="{{ route('tickets.assign', $ticket) }}" class="space-y-3">
                            @csrf
                            <select name="technician_id" required class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">
                                <option value="">Pilih teknisi...</option>
                                @foreach ($technicians as $technician)
                                    <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                                @endforeach
                            </select>
                            @error('technician_id')
                                <p class="text-xs text-danger">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active">Tugaskan</button>
                        </form>
                    </div>
                @endcan

                @can('claimTicket', $ticket)
                    <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Klaim Tiket</h2>
                        <form method="POST" action="{{ route('tickets.claim', $ticket) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active">Klaim Tiket Ini</button>
                        </form>
                    </div>
                @endcan
            @endif

            @if ($canBeResolvedNow)
                @can('resolveTicket', $ticket)
                    <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Selesaikan Tiket</h2>
                        <form method="POST" action="{{ route('tickets.resolve', $ticket) }}" class="space-y-3">
                            @csrf
                            <textarea name="resolution_notes" rows="3" placeholder="Catatan penyelesaian (opsional)" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500">{{ old('resolution_notes') }}</textarea>
                            @error('resolution_notes')
                                <p class="text-xs text-danger">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="w-full rounded-lg bg-success px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-success-active">Tandai Selesai</button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>
    </div>
</x-app-layout>
