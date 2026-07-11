@props(['label'])

<div {{ $attributes->class(['border-b border-gray-100 px-4 py-3 last:border-b-0 dark:border-gray-700 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6']) }}>
    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</dt>
    <dd class="mt-1 text-sm text-gray-900 dark:text-white sm:col-span-2 sm:mt-0">{{ $slot }}</dd>
</div>
