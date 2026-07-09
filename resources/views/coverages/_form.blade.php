@php
    $coverage ??= null;
@endphp

<div class="space-y-4">
    <div>
        <label for="pop_id" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">PoP</label>
        <select
            id="pop_id"
            name="pop_id"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white"
        >
            <option value="">Pilih PoP</option>
            @foreach ($pops as $pop)
                <option value="{{ $pop->id }}" @selected((int) old('pop_id', $coverage?->pop_id) === $pop->id)>{{ $pop->name }} ({{ $pop->code }})</option>
            @endforeach
        </select>
        @error('pop_id')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Coverage</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $coverage?->name) }}"
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
        >{{ old('description', $coverage?->description) }}</textarea>
        @error('description')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>
</div>
