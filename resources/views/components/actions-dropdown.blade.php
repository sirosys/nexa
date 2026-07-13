@props(['label' => 'Aksi'])

<div x-data="{ open: false }" class="relative inline-block text-left">
    <button
        @click="open = !open"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
    >
        {{ $label }}
        <x-icon name="chevron-down" size="4" />
    </button>

    <div
        x-show="open"
        @click.outside="open = false"
        x-transition
        class="absolute right-0 z-20 mt-2 w-44 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800"
        style="display: none;"
    >
        {{ $slot }}
    </div>
</div>
