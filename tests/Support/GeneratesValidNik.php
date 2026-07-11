<?php

namespace Tests\Support;

use App\Models\UserDetail;

/**
 * NIK bukan input bebas (lihat CLAUDE.md "User") — cuma NIK yang bisa
 * di-parse UserDetail::parseNik() yang diterima. Dipakai test manapun yang
 * butuh customer dengan NIK valid (mis. gate wajib NIK+KTP sebelum
 * registrasi Service, lihat CLAUDE.md "Service").
 */
trait GeneratesValidNik
{
    private function validNik(): string
    {
        do {
            $region = fake()->numerify('######');
            $dob = fake()->dateTimeBetween('-55 years', '-18 years');
            $day = (int) $dob->format('d') + (fake()->boolean() ? 40 : 0);

            $nik = $region
                .str_pad((string) $day, 2, '0', STR_PAD_LEFT)
                .$dob->format('m')
                .$dob->format('y')
                .fake()->numerify('####');
        } while (UserDetail::parseNik($nik) === null);

        return $nik;
    }
}
