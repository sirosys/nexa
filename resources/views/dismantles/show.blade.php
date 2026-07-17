@php
    $statusBadges = [
        'active' => ['label' => 'Aktif', 'class' => 'bg-success-light text-success dark:bg-success/10'],
        'suspended' => ['label' => 'Suspended', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'pending_dismantle' => ['label' => 'Antre Dismantle', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'dismantling' => ['label' => 'Sedang Dismantle', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'dismantled' => ['label' => 'Dibongkar', 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'],
    ];
    $badge = $statusBadges[$service->status] ?? ['label' => $service->status, 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'];
    $viewer = auth()->user();
    $dismantle = $service->dismantle;
@endphp

<x-app-layout :title="'Dismantle — ' . $service->code . ' — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <a href="{{ route('dismantles.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"><x-icon name="arrow-left" size="4" />Kembali ke Dismantle</a>
        <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $service->code }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $service->user?->name }}</p>
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
                    <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $badge['label'] }}</span>
                </x-detail-row>
                <x-detail-row label="Pelanggan">
                    @if ($service->user)
                        <a href="{{ route('users.show', $service->user) }}" class="font-medium text-primary hover:underline">{{ $service->user->name }}</a>
                        <span class="text-gray-500 dark:text-gray-400">({{ $service->user->phone }})</span>
                    @else
                        —
                    @endif
                </x-detail-row>
                <x-detail-row label="Alamat">{{ $service->address }}</x-detail-row>
                <x-detail-row label="Paket">{{ $service->package?->name ?? '—' }}</x-detail-row>
                <x-detail-row label="Coverage">{{ $service->coverage?->name ?? '—' }}</x-detail-row>
                <x-detail-row label="Diantrekan Oleh">{{ $dismantle?->queuedBy?->name ?? 'Otomatis (sistem)' }}</x-detail-row>
                <x-detail-row label="Teknisi">{{ $dismantle?->technician?->name ?? 'Belum ditugaskan' }}</x-detail-row>
                <x-detail-row label="Ditugaskan Oleh">{{ $dismantle?->assignedBy?->name ?? ($dismantle?->technician ? 'Klaim sendiri' : '—') }}</x-detail-row>
                <x-detail-row label="Diklaim/Ditugaskan Pada">{{ $dismantle?->claimed_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
                <x-detail-row label="Catatan">{{ $dismantle?->notes ?? '—' }}</x-detail-row>
                <x-detail-row label="Foto Bukti Pembongkaran">
                    @if ($dismantle?->photo)
                        <a href="{{ route('secure.dismantle-photo', $service) }}" target="_blank" class="font-medium text-primary hover:underline">Lihat foto</a>
                    @else
                        —
                    @endif
                </x-detail-row>
                <x-detail-row label="Selesai Pada">{{ $dismantle?->completed_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            </dl>
        </div>

        <div class="space-y-6">
            @if ($service->status === \App\Models\Service::STATUS_PENDING_DISMANTLE && $viewer->isSuperadmin())
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Tugaskan Teknisi</h2>
                    <form method="POST" action="{{ route('dismantles.assign', $service) }}" class="space-y-3">
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
            @endif

            @if ($service->status === \App\Models\Service::STATUS_PENDING_DISMANTLE && $viewer->isTechnician())
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Klaim Job</h2>
                    <form method="POST" action="{{ route('dismantles.claim', $service) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]">Klaim Dismantle Ini</button>
                    </form>
                </div>
            @endif

            @if ($service->status === \App\Models\Service::STATUS_DISMANTLING && $viewer->isTechnician() && $dismantle?->technician_id === $viewer->id)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Selesaikan Dismantle</h2>
                    <form method="POST" action="{{ route('dismantles.complete', $service) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Foto Bukti Pembongkaran</label>
                            <input type="file" name="photo" accept="image/*" required class="w-full text-sm text-gray-500 dark:text-gray-400">
                            @error('photo')
                                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Catatan</label>
                            <textarea name="notes" rows="3" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <button type="submit" class="w-full rounded-xl bg-success px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-success/25 transition hover:bg-success-active hover:shadow-md active:scale-[0.98]">Selesaikan Dismantle</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
