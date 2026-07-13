@props(['label'])

<div {{ $attributes->class(['border-b border-gray-100 px-6 py-4 transition last:border-b-0 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/[0.02] sm:grid sm:grid-cols-3 sm:gap-4']) }}>
    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500 sm:pt-0.5">{{ $label }}</dt>
    <dd class="mt-1 text-sm font-medium text-gray-900 dark:text-white sm:col-span-2 sm:mt-0">{{ $slot }}</dd>
</div>
