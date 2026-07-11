@php
    $roleBadges = [
        'superadmin' => ['label' => 'Superadmin', 'class' => 'bg-danger-light text-danger dark:bg-danger/10'],
        'technician' => ['label' => 'Teknisi', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'finance' => ['label' => 'Finance', 'class' => 'bg-success-light text-success dark:bg-success/10'],
        'sales' => ['label' => 'Sales', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'customer' => ['label' => 'Pelanggan', 'class' => 'bg-primary-light text-primary dark:bg-primary/10'],
    ];
    $role = $user->getRoleNames()->first();
    $userDetails = $user->userDetails;
@endphp

<x-app-layout :title="'Detail Pengguna — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('users.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Pengguna</a>
            <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $user->name }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $user->code ?? '—' }}</p>
        </div>

        <a
            href="{{ route('users.edit', $user) }}"
            class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
        >
            Ubah
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <dl>
            <x-detail-row label="Kode">{{ $user->code ?? '—' }}</x-detail-row>
            <x-detail-row label="Nama">{{ $user->name }}</x-detail-row>
            <x-detail-row label="Telepon">{{ $user->phone }}</x-detail-row>
            <x-detail-row label="Role">
                @if ($role && isset($roleBadges[$role]))
                    <span class="inline-flex items-center rounded-full {{ $roleBadges[$role]['class'] }} px-2.5 py-1 text-xs font-medium">{{ $roleBadges[$role]['label'] }}</span>
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="NIK">{{ $userDetails?->nik ?? '—' }}</x-detail-row>
            <x-detail-row label="Jenis Kelamin">
                @if ($userDetails?->gender)
                    {{ $userDetails->gender === 'female' ? 'Perempuan' : 'Laki-laki' }}
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Tanggal Lahir">{{ $userDetails?->birth_date?->locale('id')->translatedFormat('d F Y') ?? '—' }}</x-detail-row>
            <x-detail-row label="Foto KTP">
                @if ($userDetails?->ktp_photo)
                    <img src="{{ route('secure.ktp', $user) }}" alt="Foto KTP" class="h-24 rounded-lg border border-gray-300 object-cover dark:border-gray-600">
                @else
                    —
                @endif
            </x-detail-row>
            <x-detail-row label="Login Terakhir">{{ $user->last_login_at?->locale('id')->translatedFormat('d F Y, H:i') ?? 'Belum pernah login' }}</x-detail-row>
            <x-detail-row label="Terdaftar Sejak">{{ $user->created_at?->locale('id')->translatedFormat('d F Y, H:i') }}</x-detail-row>
        </dl>
    </div>
</x-app-layout>
