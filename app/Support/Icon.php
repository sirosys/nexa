<?php

namespace App\Support;

class Icon
{
    private static ?array $paths = null;

    public static function path(string $name): ?string
    {
        self::$paths ??= require resource_path('icons/outline.php');

        return self::$paths[$name] ?? null;
    }
}
