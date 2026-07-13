@php
    // Kelas badge/avatar ditulis literal (bukan interpolasi) supaya terdeteksi
    // content-scanner Tailwind v4 saat build — sama seperti dashboard.
    $roleBadges = [
        'superadmin' => ['label' => 'Superadmin', 'class' => 'bg-danger-light text-danger dark:bg-danger/10', 'avatar' => 'bg-danger'],
        'technician' => ['label' => 'Teknisi', 'class' => 'bg-warning-light text-warning dark:bg-warning/10', 'avatar' => 'bg-warning'],
        'finance' => ['label' => 'Finance', 'class' => 'bg-success-light text-success dark:bg-success/10', 'avatar' => 'bg-success'],
        'sales' => ['label' => 'Sales', 'class' => 'bg-info-light text-info dark:bg-info/10', 'avatar' => 'bg-info'],
        'customer' => ['label' => 'Pelanggan', 'class' => 'bg-primary-light text-primary dark:bg-primary/10', 'avatar' => 'bg-primary'],
    ];
@endphp

<x-app-layout :title="'Pengguna — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Pengguna</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Seluruh akun NEXA — pelanggan maupun staff/admin.</p>
        </div>

        <a
            href="{{ route('users.create') }}"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
        >
            <x-icon name="plus" size="4" />
            Tambah Pengguna
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm ring-1 ring-black/[0.03] dark:border-gray-700 dark:bg-gray-800 dark:ring-white/[0.02]">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('users.index') }}" class="flex flex-1 flex-wrap items-center gap-3">
                <div class="relative flex-1 sm:max-w-xs">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <x-icon name="magnifying-glass" size="4" />
                    </span>
                    <input
                        type="text"
                        name="q"
                        value="{{ $q }}"
                        placeholder="Cari nama, telepon, atau kode..."
                        class="w-full rounded-lg border border-gray-300 bg-transparent py-2 pl-9 pr-3 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                    >
                </div>

                <select
                    name="role"
                    onchange="this.form.submit()"
                    class="rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">Semua Role</option>
                    @foreach ($roleBadges as $value => $data)
                        <option value="{{ $value }}" @selected($role === $value)>{{ $data['label'] }}</option>
                    @endforeach
                </select>

                @if ($role !== '')
                    <a href="{{ route('users.index', ['q' => $q]) }}" class="text-sm font-semibold text-gray-500 hover:text-primary dark:text-gray-400">
                        Reset
                    </a>
                @endif
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3">Pengguna</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3">NIK</th>
                        <th class="px-4 py-3">Login Terakhir</th>
                        <th class="px-4 py-3">Terdaftar</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($users as $user)
                        @php $userRole = $user->getRoleNames()->first(); @endphp
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $roleBadges[$userRole]['avatar'] ?? 'bg-gray-400' }} text-sm font-semibold text-white">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('users.show', $user) }}" class="block truncate font-semibold text-gray-900 hover:text-primary dark:text-white">{{ $user->name }}</a>
                                        <span class="block truncate text-xs text-gray-500 dark:text-gray-400">{{ $user->phone }} @if($user->code) &middot; {{ $user->code }} @endif</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($userRole && isset($roleBadges[$userRole]))
                                    <span class="inline-flex items-center rounded-full {{ $roleBadges[$userRole]['class'] }} px-3 py-1 text-[13px] font-semibold">{{ $roleBadges[$userRole]['label'] }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-[13px] font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->userDetails->nik ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($user->last_login_at)
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                        {{ $user->last_login_at->diffForHumans() }}
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">Belum pernah</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->created_at?->translatedFormat('d M Y') }}</td>
                            <td class="px-4 py-3 text-right">
                                <x-actions-dropdown>
                                    <a href="{{ route('users.show', $user) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">
                                        <x-icon name="eye" size="4" class="text-gray-400" />
                                        Detail
                                    </a>
                                    <a href="{{ route('users.edit', $user) }}" class="flex items-center gap-2.5 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/5">
                                        <x-icon name="pencil-square" size="4" class="text-gray-400" />
                                        Ubah
                                    </a>
                                    <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus pengguna ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2 text-left text-sm font-medium text-danger hover:bg-danger-light dark:hover:bg-danger/10">
                                            <x-icon name="trash" size="4" />
                                            Hapus
                                        </button>
                                    </form>
                                </x-actions-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada pengguna.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="border-t border-gray-300 p-4 dark:border-gray-700">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
