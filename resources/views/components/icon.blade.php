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
{{--
    Ikon "Stockholm Icons" (Metronic v7.0.0 demo1) — dua-lapis (duotone): tiap
    file punya 1-2 <path> ber-fill="#000000" literal, salah satunya opacity 0.3
    untuk lapisan bayangan. Warna sebenarnya TIDAK ditentukan di sini, tapi lewat
    CSS global `.nexa-icon [fill]:not([fill="none"]) { fill: currentColor; }`
    (resources/css/app.css) — jadi ikon otomatis ikut warna teks (text-*) di
    elemen manapun, sambil tetap mempertahankan efek duotone dari opacity yang
    sudah ditulis di file aslinya. Jangan hardcode warna lewat `fill=` di sini.
--}}
@if ($path)
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        aria-hidden="true"
        {{ $attributes->class(['nexa-icon', $sizeClass, 'shrink-0']) }}
    >{!! $path !!}</svg>
@endif
