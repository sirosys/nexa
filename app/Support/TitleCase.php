<?php

namespace App\Support;

use Illuminate\Support\Str;

class TitleCase
{
    /**
     * Normalize free text to Title Case (each word capitalized), collapsing
     * repeated whitespace. Dipakai di mana pun input teks bebas dinormalisasi
     * tampilannya (nama user, alamat, nama komplek — UserRequest,
     * QuickCreateCustomerRequest, ServiceRequest) supaya konsisten terlepas
     * dari bagaimana staff mengetiknya.
     */
    public static function normalize(string $raw): string
    {
        return Str::title(preg_replace('/\s+/', ' ', trim($raw)));
    }
}
