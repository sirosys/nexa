@props(['name', 'size' => 5])
@php
    $path = \App\Support\Icon::path($name);

    // Kelas ukuran ditulis literal (bukan interpolasi "h-{$size} w-{$size}")
    // supaya tetap terdeteksi content-scanner Tailwind v4 saat build — pola
    // sama yang sudah dipakai dashboard/badge (lihat CLAUDE.md "Referensi Desain UI").
    $sizes = [
        3 => 'h-3 w-3',
        3.5 => 'h-3.5 w-3.5',
        4 => 'h-4 w-4',
        5 => 'h-5 w-5',
        6 => 'h-6 w-6',
        7 => 'h-7 w-7',
        8 => 'h-8 w-8',
        10 => 'h-10 w-10',
        12 => 'h-12 w-12',
    ];
    $sizeClass = $sizes[$size] ?? $sizes[5];
@endphp
@if ($path)
    <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        aria-hidden="true"
        {{ $attributes->class([$sizeClass, 'shrink-0']) }}
    >{!! $path !!}</svg>
@endif
