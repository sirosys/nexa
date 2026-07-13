<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value', 'type', 'group', 'label', 'description', 'updated_by'])]
class Setting extends Model
{
    /**
     * Sumber kebenaran runtime untuk aturan bisnis yang bisa diubah staff
     * lewat /settings — dipanggil di pemanggil yang dulu langsung
     * config('...'), dengan config() tetap dipakai sebagai $default kalau
     * baris belum pernah di-seed (mis. migration data belum jalan).
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('key', $key)->first();

        if (! $setting || $setting->value === null) {
            return $default;
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }
}
