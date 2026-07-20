@php
    // Fallback map label modul Indonesia — pola sama $groupLabels di
    // settings/index.blade.php. Modul yang belum ada di sini otomatis dapat
    // fallback Str::headline(), tidak perlu selalu diupdate begitu modul
    // baru menambah permission.
    $moduleLabels = [
        'users' => 'Pengguna',
        'plans' => 'Plan',
        'products' => 'Produk',
        'packages' => 'Paket',
        'sites' => 'Site',
        'coverages' => 'Coverage',
        'services' => 'Layanan',
        'installations' => 'Instalasi',
        'dismantles' => 'Dismantle',
        'sales' => 'Penjualan',
        'tickets' => 'Tiket',
        'inventory' => 'Inventaris',
        'vendors' => 'Vendor',
        'purchase_orders' => 'Purchase Order',
        'settings' => 'Pengaturan',
        'audit_logs' => 'Log Aktivitas',
        'reports' => 'Laporan',
        'roles' => 'Role & Permission',
    ];
@endphp

<x-app-layout :title="'Ubah Role — ' . config('app.name', 'NEXA')">
    <a href="{{ route('roles.index') }}" class="mb-4 inline-flex items-center gap-1.5 text-sm font-semibold text-gray-600 hover:text-primary dark:text-gray-300">
        <x-icon name="arrow-left" size="4" />
        Kembali ke Role & Permission
    </a>

    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $role->name }}</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Atur permission untuk role ini.</p>
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

    @if ($isSuperadmin)
        <div class="mb-6 rounded-lg border border-info/20 bg-info-light px-4 py-3 text-sm text-info dark:border-info/30 dark:bg-info/10">
            Role Superadmin selalu memiliki seluruh permission secara otomatis dan tidak bisa diubah lewat halaman ini.
        </div>
    @elseif ($isBuiltInRole && $role->permissions_managed_by_seeder)
        <div class="mb-6 rounded-lg border border-info/20 bg-info-light px-4 py-3 text-sm text-info dark:border-info/30 dark:bg-info/10">
            Role ini masih mengikuti default sistem (dikelola otomatis lewat kode). Menyimpan perubahan di sini akan
            melepas role ini dari sinkronisasi otomatis — permission barunya sepenuhnya dikelola lewat halaman ini
            mulai saat itu, termasuk permission baru dari modul mendatang (perlu dicentang manual, tidak lagi otomatis).
        </div>
    @elseif ($isBuiltInRole)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:border-gray-700 dark:bg-white/5 dark:text-gray-300">
            <span>Role ini sudah lepas dari sinkronisasi otomatis — dikelola penuh lewat halaman ini.</span>
            <form method="POST" action="{{ route('roles.reset-to-default', $role) }}" onsubmit="return confirm('Kembalikan permission role ini ke default sistem? Perubahan manual yang sudah dibuat akan hilang.');">
                @csrf
                <button type="submit" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition hover:bg-white dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    Kembalikan ke Default Sistem
                </button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('roles.update', $role) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
            <div class="border-b border-gray-300 p-4 dark:border-gray-700">
                <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Role</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="{{ old('name', $role->name) }}"
                    @if ($isBuiltInRole) disabled @endif
                    class="block w-full max-w-xs rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                >
                @if ($isBuiltInRole)
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Nama role bawaan sistem tidak bisa diubah.</p>
                @endif
                @error('name')
                    <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                @enderror
            </div>
        </div>

        @foreach ($permissionGroups as $module => $permissions)
            <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-300 p-4 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $moduleLabels[$module] ?? \Illuminate\Support\Str::headline($module) }}</h2>
                </div>
                <div class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissions as $permission)
                        @php $checked = $isSuperadmin || in_array($permission->name, old('permissions', $rolePermissionNames)); @endphp
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                name="permissions[]"
                                value="{{ $permission->name }}"
                                @checked($checked)
                                @if ($isSuperadmin) disabled @endif
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600"
                            >
                            {{ $permission->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        @unless ($isSuperadmin)
            <div>
                <button
                    type="submit"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
                >
                    Simpan Permission
                </button>
            </div>
        @endunless
    </form>
</x-app-layout>
