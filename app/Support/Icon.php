<?php

namespace App\Support;

class Icon
{
    private static ?array $map = null;

    private static array $cache = [];

    public static function path(string $name): ?string
    {
        if (array_key_exists($name, self::$cache)) {
            return self::$cache[$name];
        }

        self::$map ??= require resource_path('icons/metronic-map.php');

        $relative = self::$map[$name] ?? null;

        return self::$cache[$name] = $relative ? self::extractInnerMarkup($relative) : null;
    }

    /**
     * File aslinya adalah SVG lengkap (<svg ...>...</svg>>) — cuma isi <g>...</g>
     * di dalamnya yang dipakai, karena wrapper <svg> (viewBox/xmlns/ukuran) sudah
     * ditulis ulang oleh komponen <x-icon> sendiri supaya konsisten lintas ikon.
     */
    private static function extractInnerMarkup(string $relative): ?string
    {
        $file = resource_path('icons/metronic/'.$relative);

        if (! is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if (! preg_match('/<g[^>]*>.*<\/g>/s', $contents, $matches)) {
            return null;
        }

        return $matches[0];
    }
}
