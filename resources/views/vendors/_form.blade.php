@php
    $vendor ??= null;
@endphp

<div class="space-y-4">
    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nama Vendor</label>
        <input
            type="text"
            id="name"
            name="name"
            value="{{ old('name', $vendor?->name) }}"
            required
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('name')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="contact_person" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Kontak Person</label>
        <input
            type="text"
            id="contact_person"
            name="contact_person"
            value="{{ old('contact_person', $vendor?->contact_person) }}"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >
        @error('contact_person')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Telepon</label>
            <input
                type="text"
                id="phone"
                name="phone"
                value="{{ old('phone', $vendor?->phone) }}"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('phone')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
            <input
                type="email"
                id="email"
                name="email"
                value="{{ old('email', $vendor?->email) }}"
                class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
            >
            @error('email')
                <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div>
        <label for="address" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Alamat</label>
        <textarea
            id="address"
            name="address"
            rows="2"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >{{ old('address', $vendor?->address) }}</textarea>
        @error('address')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="notes" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Catatan</label>
        <textarea
            id="notes"
            name="notes"
            rows="2"
            class="block w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary dark:border-gray-600 dark:text-white dark:placeholder:text-gray-500"
        >{{ old('notes', $vendor?->notes) }}</textarea>
        @error('notes')
            <p class="mt-1.5 text-sm text-danger">{{ $message }}</p>
        @enderror
    </div>
</div>
