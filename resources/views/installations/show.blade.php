@php
    $statusBadges = [
        'pending_payment' => ['label' => 'Menunggu Pembayaran', 'class' => 'bg-warning-light text-warning dark:bg-warning/10'],
        'pending_installation' => ['label' => 'Menunggu Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'installing' => ['label' => 'Sedang Instalasi', 'class' => 'bg-info-light text-info dark:bg-info/10'],
        'active' => ['label' => 'Aktif', 'class' => 'bg-success-light text-success dark:bg-success/10'],
    ];
    $badge = $statusBadges[$service->status] ?? ['label' => $service->status, 'class' => 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'];
    $viewer = auth()->user();
    $activation = $service->activation;
@endphp

<x-app-layout :title="'Instalasi — ' . $service->code . ' — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <a href="{{ route('installations.index') }}" class="text-sm font-medium text-primary hover:underline">&larr; Kembali ke Instalasi</a>
        <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-white">{{ $service->code }}</h1>
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
        <div class="rounded-2xl border border-gray-300 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:col-span-2">
            <dl>
                <x-detail-row label="Status">
                    <span class="inline-flex items-center rounded-full {{ $badge['class'] }} px-2.5 py-1 text-xs font-medium">{{ $badge['label'] }}</span>
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
                <x-detail-row label="Teknisi">{{ $activation?->installer?->name ?? 'Belum ditugaskan' }}</x-detail-row>
                <x-detail-row label="Ditugaskan Oleh">{{ $activation?->assignedBy?->name ?? ($activation?->installer ? 'Klaim sendiri' : '—') }}</x-detail-row>
                <x-detail-row label="Diklaim/Ditugaskan Pada">{{ $activation?->claimed_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
                <x-detail-row label="ODP Port">{{ $activation?->odp_port ?? '—' }}</x-detail-row>
                <x-detail-row label="Panjang Kabel">{{ $activation?->cable_length ? $activation->cable_length.' meter' : '—' }}</x-detail-row>
                <x-detail-row label="Catatan">{{ $activation?->notes ?? '—' }}</x-detail-row>
                <x-detail-row label="Foto Bukti Instalasi">
                    @if ($activation?->photo)
                        <a href="{{ route('secure.installation-photo', $service) }}" target="_blank" class="font-medium text-primary hover:underline">Lihat foto</a>
                    @else
                        —
                    @endif
                </x-detail-row>
                <x-detail-row label="Selesai Pada">{{ $activation?->completed_at?->locale('id')->translatedFormat('d F Y, H:i') ?? '—' }}</x-detail-row>
            </dl>
        </div>

        <div class="space-y-6">
            @if ($service->status === \App\Models\Service::STATUS_PENDING_INSTALLATION && $viewer->isSuperadmin())
                <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Tugaskan Teknisi</h2>
                    <form method="POST" action="{{ route('installations.assign', $service) }}" class="space-y-3">
                        @csrf
                        <select name="installer_id" required class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">
                            <option value="">Pilih teknisi...</option>
                            @foreach ($technicians as $technician)
                                <option value="{{ $technician->id }}">{{ $technician->name }}</option>
                            @endforeach
                        </select>
                        @error('installer_id')
                            <p class="text-xs text-danger">{{ $message }}</p>
                        @enderror
                        <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active">Tugaskan</button>
                    </form>
                </div>
            @endif

            @if ($service->status === \App\Models\Service::STATUS_PENDING_INSTALLATION && $viewer->isTechnician())
                <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Klaim Job</h2>
                    <form method="POST" action="{{ route('installations.claim', $service) }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-active">Klaim Instalasi Ini</button>
                    </form>
                </div>
            @endif

            @if ($service->status === \App\Models\Service::STATUS_INSTALLING && $viewer->isTechnician() && $activation?->installer_id === $viewer->id)
                <div class="rounded-2xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="mb-3 text-sm font-semibold text-gray-900 dark:text-white">Selesaikan Instalasi</h2>
                    <form
                        method="POST"
                        action="{{ route('installations.complete', $service) }}"
                        enctype="multipart/form-data"
                        class="space-y-3"
                        x-data="{
                            items: {{ \Illuminate\Support\Js::from($inventoryItems) }},
                            rows: [],
                            addRow() { this.rows.push({ inventory_item_id: '', quantity: 1, serial_number: '' }); },
                            removeRow(index) { this.rows.splice(index, 1); },
                            itemFor(id) { return this.items.find((i) => i.id === Number(id)) ?? null; },
                        }"
                    >
                        @csrf
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">ODP Port</label>
                            <input type="text" name="odp_port" value="{{ old('odp_port') }}" required maxlength="20" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">
                            @error('odp_port')
                                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Panjang Kabel (meter)</label>
                            <input type="number" step="0.1" min="0" name="cable_length" value="{{ old('cable_length') }}" class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white">
                            @error('cable_length')
                                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Equipment Terpakai (opsional)</label>
                                <button type="button" @click="addRow()" class="text-xs font-medium text-primary hover:underline">+ Tambah</button>
                            </div>
                            <template x-if="items.length === 0">
                                <p class="text-xs text-gray-500 dark:text-gray-400">Belum ada stok inventaris tersedia.</p>
                            </template>
                            <div class="space-y-2">
                                <template x-for="(row, index) in rows" :key="index">
                                    <div class="flex items-center gap-2">
                                        <select
                                            :name="'equipment[' + index + '][inventory_item_id]'"
                                            x-model.number="row.inventory_item_id"
                                            @change="row.quantity = 1; row.serial_number = ''"
                                            class="flex-1 rounded-lg border border-gray-300 bg-transparent px-2 py-1.5 text-xs text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                        >
                                            <option value="">Pilih item</option>
                                            <template x-for="item in items" :key="item.id">
                                                <option :value="item.id" x-text="item.name + (item.is_serialized ? '' : ' (stok: ' + item.quantity + ')')"></option>
                                            </template>
                                        </select>

                                        <template x-if="itemFor(row.inventory_item_id)?.is_serialized">
                                            <select
                                                :name="'equipment[' + index + '][serial_number]'"
                                                x-model="row.serial_number"
                                                class="w-32 rounded-lg border border-gray-300 bg-transparent px-2 py-1.5 text-xs text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                            >
                                                <option value="">Pilih serial</option>
                                                <template x-for="serial in itemFor(row.inventory_item_id)?.serials ?? []" :key="serial">
                                                    <option :value="serial" x-text="serial"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="row.inventory_item_id && !itemFor(row.inventory_item_id)?.is_serialized">
                                            <input
                                                type="number"
                                                min="1"
                                                :name="'equipment[' + index + '][quantity]'"
                                                x-model.number="row.quantity"
                                                class="w-20 rounded-lg border border-gray-300 bg-transparent px-2 py-1.5 text-xs text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
                                            >
                                        </template>

                                        <button type="button" @click="removeRow(index)" class="text-xs font-medium text-danger hover:underline">Hapus</button>
                                    </div>
                                </template>
                            </div>
                            @error('equipment')
                                <p class="mt-1 text-xs text-danger">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400">Foto Bukti Instalasi</label>
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
                        <button type="submit" class="w-full rounded-lg bg-success px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-success-active">Selesaikan & Aktifkan</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
