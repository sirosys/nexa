@php
    // Kelas badge ditulis literal (bukan interpolasi) supaya terdeteksi
    // content-scanner Tailwind v4 saat build — sama seperti dashboard.
    $roleBadges = [
        'superadmin' => ['label' => 'Superadmin', 'class' => 'bg-danger-light text-danger dark:bg-danger/10'],
        'technician' => ['label' => 'Teknisi', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'finance' => ['label' => 'Finance', 'class' => 'bg-success-light text-success dark:bg-success/10'],
        'sales' => ['label' => 'Sales', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'customer' => ['label' => 'Pelanggan', 'class' => 'bg-primary-light text-primary dark:bg-primary/10'],
    ];
@endphp

<x-app-layout :title="'Pengguna — ' . config('app.name', 'NEXA')">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Pengguna</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Seluruh akun NEXA — pelanggan maupun staff/admin.</p>
        </div>

        <a
            href="{{ route('users.create') }}"
            class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active"
        >
            Tambah Pengguna
        </a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
        <div class="border-b border-gray-300 p-4 dark:border-gray-700">
            <form method="GET" action="{{ route('users.index') }}">
                <input
                    type="text"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Cari nama, telepon, atau kode..."
                    class="w-full max-w-sm rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-300 text-xs uppercase text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Kode</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Telepon</th>
                        <th class="px-4 py-3">NIK</th>
                        <th class="px-4 py-3">Role</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($users as $user)
                        <tr>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->code ?? '—' }}</td>
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->phone }}</td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->userDetails->nik ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @php $role = $user->getRoleNames()->first(); @endphp
                                @if ($role && isset($roleBadges[$role]))
                                    <span class="inline-flex items-center rounded-full {{ $roleBadges[$role]['class'] }} px-2.5 py-1 text-xs font-medium">{{ $roleBadges[$role]['label'] }}</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-500 dark:bg-gray-700 dark:text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('users.show', $user) }}" class="font-medium text-gray-600 hover:underline dark:text-gray-300">Detail</a>
                                    <a href="{{ route('users.edit', $user) }}" class="font-medium text-primary hover:underline">Ubah</a>
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Hapus pengguna ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-medium text-danger hover:underline">Hapus</button>
                                    </form>
                                </div>
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
