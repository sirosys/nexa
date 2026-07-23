@php
    // Label Indonesia rapi cuma untuk 4 role bawaan sistem — pola & isi SAMA
    // PERSIS $roleLabels di users/_form.blade.php (lihat CLAUDE.md
    // "Authorization / Role & Permission" — wajib diupdate berbarengan kalau
    // label role berubah lagi). Role custom fallback ke Str::headline().
    $roleLabels = [
        'superadmin' => 'Superadmin',
        'technician' => 'Teknisi',
        'finance' => 'NOC',
        'customer' => 'Pelanggan',
    ];
@endphp

<x-app-layout :title="'Role &amp; Permission — ' . config('app.name', 'NEXA')">
    <div x-data="{ showCreateModal: {{ \Illuminate\Support\Js::from($errors->any()) }} }">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Role & Permission</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola role staff dan permission-nya masing-masing.</p>
            </div>

            @can('create', \Spatie\Permission\Models\Role::class)
                <button
                    type="button"
                    @click="showCreateModal = true"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98] inline-flex items-center gap-2"
                >
                    <x-icon name="plus" size="4" />
                    Tambah Role
                </button>
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

        <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:border-gray-700 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Nama Role</th>
                            <th class="px-4 py-3">Jumlah Permission</th>
                            <th class="px-4 py-3">Jumlah Pengguna</th>
                            <th class="px-4 py-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($roles as $role)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $roleLabels[$role->name] ?? \Illuminate\Support\Str::headline($role->name) }}</span>
                                        @if ($role->is_system)
                                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-500 dark:bg-gray-700 dark:text-gray-400">Sistem</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $role->permissions_count }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $role->user_count }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('update', $role)
                                            <x-row-action :href="route('roles.edit', $role)" icon="pencil-square" label="Ubah Permission" variant="primary" />
                                        @endcan

                                        @can('delete', $role)
                                            @if (! $role->is_system && $role->user_count === 0)
                                                <form method="POST" action="{{ route('roles.destroy', $role) }}" onsubmit="return confirm('Hapus role ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-row-action icon="trash" label="Hapus" variant="danger" />
                                                </form>
                                            @else
                                                <span
                                                    title="{{ $role->is_system ? 'Role sistem tidak bisa dihapus.' : 'Masih dipakai '.$role->user_count.' pengguna, tidak bisa dihapus.' }}"
                                                    class="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg text-gray-300 dark:text-gray-600"
                                                >
                                                    <x-icon name="trash" size="5" />
                                                    <span class="sr-only">Tidak bisa dihapus</span>
                                                </span>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada role.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Modal "Tambah Role" — cuma minta nama, permission diatur di
            halaman edit setelah role dibuat (terlalu banyak permission untuk
            dimuat di modal kecil ini, lihat CLAUDE.md "Authorization / Role
            & Permission"). Pola sama modal "Tambah Pengguna" di /users. --}}
        <div
            x-show="showCreateModal"
            x-cloak
            class="fixed inset-0 z-40 flex items-center justify-center overflow-y-auto bg-gray-900/50 p-4"
            @keydown.escape.window="showCreateModal = false"
        >
            <div
                x-show="showCreateModal"
                @click.outside="showCreateModal = false"
                class="my-8 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-gray-800"
            >
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Tambah Role</h3>
                    <button type="button" @click="showCreateModal = false" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-300">
                        <x-icon name="x-mark" size="5" />
                    </button>
                </div>

                <form method="POST" action="{{ route('roles.store') }}">
                    @csrf

                    <div>
                        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Role</label>
                        <input
                            type="text"
                            id="name"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="mis. warehouse_staff"
                            required
                            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                        >
                        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Huruf kecil, angka, underscore — diawali huruf.</p>
                        @error('name')
                            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" @click="showCreateModal = false" class="rounded-lg px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">Batal</button>
                        <button
                            type="submit"
                            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
                        >
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
