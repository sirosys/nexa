<?php

namespace App\Http\Requests;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class SettingUpdateRequest extends FormRequest
{
    /**
     * Otorisasi ditangani eksplisit di SettingController (pola sama
     * action non-resource lain di project ini), bukan di sini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rules dibangun dinamis dari baris `settings` yang ada di DB (bukan
     * daftar field tetap) — key setting bisa bertambah di masa depan tanpa
     * perlu menyentuh file ini, cukup rule per `type` kolom.
     *
     * Field form & rule di-key oleh `id` (numerik), BUKAN `key` (string
     * seperti `renewal.remind_days_before.invoice`) — kalau dipakai
     * langsung, titik di dalam string key itu sendiri disalahartikan
     * validator Laravel sebagai pemisah struktur nested array (dot
     * notation), bukan literal string key, dan bikin validasi berantakan.
     * SettingController::update() memetakan id balik ke `key` sungguhan.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (Setting::all() as $setting) {
            $rules["settings.{$setting->id}"] = match ($setting->type) {
                'integer' => ['required', 'integer', 'min:1'],
                'boolean' => ['required', 'boolean'],
                default => ['required', 'string'],
            };
        }

        return $rules;
    }
}
