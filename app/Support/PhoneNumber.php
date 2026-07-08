<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Normalize a locally-typed Indonesian phone number into the canonical
     * storage format: country code 62 + subscriber number, no leading 0.
     *
     * Examples: "81234567890" -> "6281234567890"
     *           "081234567890" -> "6281234567890" (leading trunk 0 replaced)
     *           "6281234567890" -> "6281234567890" (already normalized)
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/[^0-9]/', '', $raw);

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        return '62'.$digits;
    }
}
