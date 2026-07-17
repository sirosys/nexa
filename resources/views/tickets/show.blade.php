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
            <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Tiket</a>
            <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $ticket->code }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $ticket->subject }}</p>
        </div>

        @can('update', $ticket)
            <a
                href="{{ route('tickets.edit', $ticket) }}"
                class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
            >
            <x-icon name="pencil-square" size="4" />
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
        <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800 lg:col-span-2">
            <dl>
                <x-detail-row label="Status">
                    <span class="inline-flex items-center rounded-full {{ $statusClasses[$ticket->status] ?? 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' }} px-3 py-1 text-[13px] font-semibold">
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
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
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
                            <button type="submit" class="w-full rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]">Tugaskan</button>
                        </form>
                    </div>
                @endcan

                @can('claimTicket', $ticket)
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Klaim Tiket</h2>
                        <form method="POST" action="{{ route('tickets.claim', $ticket) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]">Klaim Tiket Ini</button>
                        </form>
                    </div>
                @endcan
            @endif

            @if ($canBeResolvedNow)
                @can('resolveTicket', $ticket)
                    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                        <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Selesaikan Tiket</h2>
                        <form method="POST" action="{{ route('tickets.resolve', $ticket) }}" class="space-y-3">
                            @csrf
                            <textarea name="resolution_notes" rows="3" placeholder="Catatan penyelesaian (opsional)" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500">{{ old('resolution_notes') }}</textarea>
                            @error('resolution_notes')
                                <p class="text-xs text-danger">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="w-full rounded-xl bg-success px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-success/25 transition hover:bg-success-active hover:shadow-md active:scale-[0.98]">Tandai Selesai</button>
                        </form>
                    </div>
                @endcan
            @endif
        </div>
    </div>
</x-app-layout>
