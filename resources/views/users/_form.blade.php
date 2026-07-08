@php
    $user ??= null;
    $userDetails = $user?->userDetails;
    // Kolom phone disimpan lengkap dengan kode negara (62...), tapi input
    // cuma menerima bagian lokal (badge +62 terpisah di depannya) — sama
    // seperti pola di auth/login.blade.php.
    $phoneLocal = $user ? preg_replace('/^62/', '', (string) $user->phone) : null;
@endphp

<div class="space-y-4">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $user?->name) }}"
            required
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('name')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nomor Telepon</label>
        <div class="flex items-center overflow-hidden rounded-lg border border-gray-300 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary dark:border-gray-600">
            <span class="border-r border-gray-300 bg-gray-50 px-3 py-2.5 text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                +62
            </span>
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
                class="block w-full border-0 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-0 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-500"
            >
        </div>
        @error('phone')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-center gap-2">
        <input
            type="checkbox"
            id="admin"
            name="admin"
            value="1"
            @checked(old('admin', $user?->admin))
            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600"
        >
        <label for="admin" class="text-sm font-medium text-gray-700 dark:text-gray-300">Admin / staff</label>
        <span class="text-xs text-gray-400 dark:text-gray-500">(bukan pelanggan — login tetap lewat OTP, bukan role/permission granular)</span>
        @error('admin')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
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
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                Jenis kelamin & tanggal lahir otomatis terisi dari NIK. NIK tidak bisa diubah lagi setelah disimpan.
            </p>
        @endif

        @error('nik')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="ktp_photo" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Foto KTP</label>

        @if ($userDetails?->ktp_photo)
            <img src="{{ route('secure.ktp', $user) }}" alt="Foto KTP" class="mb-2 h-24 rounded-lg border border-gray-300 object-cover dark:border-gray-600">
        @endif

        <input
            type="file"
            id="ktp_photo"
            name="ktp_photo"
            accept="image/*"
            class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-light file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary dark:text-gray-300"
        >
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Disimpan privat — hanya pemilik akun dan admin yang bisa melihat.</p>
        @error('ktp_photo')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>
</div>
