@php
    $groupLabels = [
        'billing' => 'Billing',
        'renewal' => 'Renewal',
        'dismantle' => 'Dismantle',
    ];
@endphp

<x-app-layout :title="'Pengaturan — ' . config('app.name', 'NEXA')">
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Pengaturan</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Aturan bisnis yang bisa diubah tanpa perlu deploy ulang aplikasi.</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-success/20 bg-success-light px-4 py-3 text-sm text-success dark:border-success/30 dark:bg-success/10">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        @foreach ($groups as $group => $settings)
            <div class="rounded-2xl border border-gray-200 bg-white shadow-[0_0_20px_0_rgba(76,87,125,0.02)] dark:border-gray-700 dark:bg-gray-800">
                <div class="border-b border-gray-300 p-4 dark:border-gray-700">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">{{ $groupLabels[$group] ?? ucfirst($group) }}</h2>
                </div>
                <div class="space-y-4 p-4">
                    @foreach ($settings as $setting)
                        <div>
                            <label for="setting-{{ $setting->id }}" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ $setting->label }}
                            </label>
                            @if ($setting->description)
                                <p class="mb-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $setting->description }}</p>
                            @endif
                            {{-- Di-key oleh id (bukan `key` string seperti
                            "renewal.remind_days_before.invoice") -- titik di
                            dalam string key itu sendiri disalahartikan
                            Laravel sebagai pemisah dot-notation kalau dipakai
                            langsung sebagai nama field/old()/@error, lihat
                            SettingUpdateRequest. --}}
                            <input
                                type="{{ $setting->type === 'integer' ? 'number' : 'text' }}"
                                id="setting-{{ $setting->id }}"
                                name="settings[{{ $setting->id }}]"
                                value="{{ old('settings.'.$setting->id, $setting->value) }}"
                                @if ($setting->type === 'integer') min="1" @endif
                                required
                                class="block w-full max-w-xs rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
                            >
                            @error('settings.'.$setting->id)
                                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div>
            <button
                type="submit"
                class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/25 transition hover:bg-primary-active hover:shadow-md active:scale-[0.98]"
            >
                Simpan Pengaturan
            </button>
        </div>
    </form>
</x-app-layout>
