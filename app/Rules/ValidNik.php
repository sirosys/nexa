<?php

namespace App\Rules;

use App\Models\UserDetail;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * NIK bukan input bebas — cuma diterima kalau bisa di-parse jadi
 * gender+birth_date lewat UserDetail::parseNik() (lihat CLAUDE.md "User").
 * Diekstrak jadi Rule sendiri supaya dipakai ulang oleh UserRequest dan
 * CompleteKycRequest tanpa duplikasi logic parsing.
 */
class ValidNik implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (UserDetail::parseNik((string) $value) === null) {
            $fail('NIK tidak valid.');
        }
    }
}
