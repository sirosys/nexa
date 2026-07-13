@props(['icon', 'label', 'variant' => 'neutral', 'href' => null])
@php
    $variants = [
        'neutral' => 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/10 dark:hover:text-gray-200',
        'primary' => 'text-primary hover:bg-primary-light dark:hover:bg-primary/10',
        'danger' => 'text-danger hover:bg-danger-light dark:hover:bg-danger/10',
    ];
    $classes = 'inline-flex h-9 w-9 items-center justify-center rounded-lg transition ' . ($variants[$variant] ?? $variants['neutral']);
@endphp
@if ($href)
    <a href="{{ $href }}" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" size="5" />
        <span class="sr-only">{{ $label }}</span>
    </a>
@else
    <button type="submit" title="{{ $label }}" {{ $attributes->class($classes) }}>
        <x-icon :name="$icon" size="5" />
        <span class="sr-only">{{ $label }}</span>
    </button>
@endif
