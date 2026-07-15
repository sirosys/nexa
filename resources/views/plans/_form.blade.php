@php
    $plan ??= null;
@endphp

<div class="space-y-4">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
        <input
            type="text"
            id="name"
            name="name"
            placeholder="Internet Basic 10 Mbps"
            value="{{ old('name', $plan?->name) }}"
            required
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('name')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Deskripsi</label>
        <textarea
            id="description"
            name="description"
            rows="3"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >{{ old('description', $plan?->description) }}</textarea>
        @error('description')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="price" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Harga per Bulan</label>
        <input
            type="number"
            id="price"
            name="price"
            step="0.01"
            min="0"
            value="{{ old('price', $plan?->price) }}"
            required
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Harga katalog reguler — dipakai sistem saat membuat tagihan perpanjangan otomatis (bukan harga promo di paket manapun).</p>
        @error('price')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>
</div>
