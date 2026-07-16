<?php

namespace App\Rules;

use App\Models\Subdistrict;
use App\Models\UserDetail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * NIK bukan input bebas — cuma diterima kalau bisa di-parse jadi
 * gender+birth_date lewat UserDetail::parseNik() (lihat CLAUDE.md "User"),
 * DAN 6 digit pertamanya (kode wilayah kecamatan) harus benar-benar
 * terdaftar di `subdistricts.district_id` (data referensi Dukcapil/
 * Kemendagri, lihat "Draft Desain Database" — tabel ini yang jadi acuan,
 * bukan whitelist kode wilayah terpisah). Diekstrak jadi Rule sendiri
 * supaya dipakai ulang oleh UserRequest dan CompleteKycRequest tanpa
 * duplikasi logic parsing.
 */
class ValidNik implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $nik = (string) $value;

        if (UserDetail::parseNik($nik) === null) {
            $fail('NIK tidak valid.');

            return;
        }

        $districtCode = (int) substr($nik, 0, 6);

        if (! Subdistrict::where('district_id', $districtCode)->exists()) {
            $fail('Kode wilayah pada NIK tidak ditemukan di database Dukcapil.');
        }
    }
}
