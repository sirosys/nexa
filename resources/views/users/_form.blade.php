@php
    $user ??= null;
    $userDetails = $user?->userDetails;
    $isCreating = $user === null;
    $existingKtpUrl = $userDetails?->ktp_photo ? route('secure.ktp', $user) : null;
    // Kolom phone disimpan lengkap dengan kode negara (62...), tapi input
    // cuma menerima bagian lokal (badge +62 terpisah di depannya) — sama
    // seperti pola di auth/login.blade.php.
    $phoneLocal = $user ? preg_replace('/^62/', '', (string) $user->phone) : null;
    $roleLabels = [
        'superadmin' => 'Superadmin',
        'technician' => 'Teknisi',
        'finance' => 'Finance',
        'sales' => 'Sales',
        'customer' => 'Pelanggan',
    ];
    $currentRole = $user?->getRoleNames()->first();

    // 'true'/'false' string (bukan boolean PHP) — atribut HTML aria-invalid
    // memang bernilai string, dan kehadirannya (bahkan "false") dipakai
    // daisyUI's .validator buat tahu field ini "sudah disentuh validasi
    // server" — lihat komponen validator daisyUI (node_modules/daisyui/
    // components/validator.css): elemen jadi merah kalau
    // [aria-invalid]:not([aria-invalid=false]), jadi bukan cuma live
    // constraint browser (:user-invalid) tapi juga error hasil submit ke
    // server (unique/ValidNik/dst, yang tidak bisa dicek client-side).
    $ariaInvalid = fn (string $field) => $errors->has($field) ? 'true' : 'false';
@endphp

<div class="space-y-4" x-data="{ photoPreview: {{ \Illuminate\Support\Js::from($existingKtpUrl) }} }">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $user?->name) }}"
            required
            aria-invalid="{{ $ariaInvalid('name') }}"
            class="input validator w-full"
        >
        @error('name')
            <p class="validator-hint">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nomor Telepon</label>
        {{-- Pola prefix di dalam input daisyUI: class `input` di <label>
            pembungkus (bukan di <input> itu sendiri), <input> polos di
            dalamnya dengan class `grow` — lihat dokumentasi daisyUI
            "Input with prefix/suffix". aria-invalid ditaruh di <input>
            asli, otomatis kepick oleh <label class="input"> lewat
            selector :has() di komponen daisyUI, tidak perlu diulang di
            elemen pembungkus. --}}
        <label class="input validator w-full">
            <span class="text-gray-400 dark:text-gray-500">+62</span>
            <input
                type="tel"
                id="phone"
                name="phone"
                inputmode="numeric"
                placeholder="81234567890"
                pattern="[0-9]*"
                title="Hanya angka"
                maxlength="15"
                value="{{ old('phone', $phoneLocal) }}"
                required
                aria-invalid="{{ $ariaInvalid('phone') }}"
                class="grow"
            >
        </label>
        @error('phone')
            <p class="validator-hint">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            placeholder="nama@contoh.com"
            value="{{ old('email', $user?->email) }}"
            required
            aria-invalid="{{ $ariaInvalid('email') }}"
            class="input validator w-full"
        >
        @error('email')
            <p class="validator-hint">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="role" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
        <select
            id="role"
            name="role"
            required
            aria-invalid="{{ $ariaInvalid('role') }}"
            class="select validator w-full"
        >
            @foreach ($roleLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('role', $currentRole) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('role')
            <p class="validator-hint">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="nik" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">NIK</label>

        @if ($userDetails?->nik)
            {{-- NIK terkunci begitu pernah tersimpan — tidak dirender sebagai
                input supaya tidak ikut ter-submit, jenis kelamin & tanggal
                lahir murni derivasi dari NIK, bukan input manual. --}}
            <input type="hidden" name="nik" value="{{ $userDetails->nik }}">
            <p class="rounded-lg border border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                {{ $userDetails->nik }}
                <span class="text-gray-400 dark:text-gray-500">(terkunci)</span>
            </p>
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                {{ $userDetails->gender === 'female' ? 'Perempuan' : 'Laki-laki' }} ·
                lahir {{ $userDetails->birth_date?->locale('id')->translatedFormat('d F Y') }}
            </p>
        @else
            <input
                type="text"
                id="nik"
                name="nik"
                inputmode="numeric"
                pattern="[0-9]*"
                maxlength="16"
                value="{{ old('nik') }}"
                {{ $isCreating ? 'required' : '' }}
                aria-invalid="{{ $ariaInvalid('nik') }}"
                class="input validator w-full"
            >
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                Jenis kelamin & tanggal lahir otomatis terisi dari NIK. NIK tidak bisa diubah lagi setelah disimpan.
            </p>
        @endif

        @error('nik')
            <p class="validator-hint">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="ktp_photo" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Foto KTP</label>

        {{-- Preview — begitu file baru dipilih, langsung diganti ke file
            terakhir yang diupload (client-side, lewat FileReader); kalau
            belum ada pilihan baru, tampilkan foto tersimpan (mode edit). --}}
        <template x-if="photoPreview">
            <img :src="photoPreview" alt="Pratinjau Foto KTP" class="mb-2 h-28 w-auto rounded-lg border border-gray-300 object-cover dark:border-gray-600">
        </template>
        <template x-if="! photoPreview">
            <div class="mb-2 flex h-28 w-44 items-center justify-center rounded-lg border border-dashed border-gray-300 text-xs text-gray-400 dark:border-gray-600 dark:text-gray-500">
                Belum ada foto
            </div>
        </template>

        <input
            type="file"
            id="ktp_photo"
            name="ktp_photo"
            accept="image/*"
            {{ $isCreating ? 'required' : '' }}
            aria-invalid="{{ $ariaInvalid('ktp_photo') }}"
            @change="
                const file = $event.target.files[0];
                if (! file) { photoPreview = {{ \Illuminate\Support\Js::from($existingKtpUrl) }}; return; }
                const reader = new FileReader();
                reader.onload = (e) => { photoPreview = e.target.result; };
                reader.readAsDataURL(file);
            "
            class="file-input validator w-full"
        >
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Disimpan privat — hanya pemilik akun dan superadmin yang bisa melihat.</p>
        @error('ktp_photo')
            <p class="validator-hint">{{ $message }}</p>
        @enderror
    </div>
</div>
