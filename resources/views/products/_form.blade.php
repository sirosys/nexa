@php
    $product ??= null;
    $typeLabels = [
        'perangkat' => 'Perangkat',
        'jasa' => 'Jasa',
        'biaya' => 'Biaya',
        'lainnya' => 'Lainnya',
    ];
@endphp

<div class="space-y-4">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $product?->name) }}"
            required
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('name')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="type" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipe</label>
        <select
            id="type"
            name="type"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
        >
            @foreach ($typeLabels as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $product?->type) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')
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
        >{{ old('description', $product?->description) }}</textarea>
        @error('description')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div>
            <label for="price" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Harga</label>
            <input
                type="number"
                id="price"
                name="price"
                step="0.01"
                min="0"
                value="{{ old('price', $product?->price) }}"
                required
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('price')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="unit" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Satuan</label>
            <input
                type="text"
                id="unit"
                name="unit"
                placeholder="pcs, bulan, dst."
                value="{{ old('unit', $product?->unit) }}"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('unit')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
