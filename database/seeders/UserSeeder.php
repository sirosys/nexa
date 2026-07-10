<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserDetail;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Distribusi role untuk 20 akun demo — customer paling banyak supaya
     * seeder lain yang butuh pelanggan sungguhan (mis. ServiceSeeder) tidak
     * kehabisan data. superadmin tidak diulang di sini, sudah ada dari
     * AdminUserSeeder.
     */
    private const ROLE_COUNTS = [
        'customer' => 12,
        'technician' => 3,
        'finance' => 2,
        'sales' => 3,
    ];

    public function run(): void
    {
        $usedNiks = [];

        foreach (self::ROLE_COUNTS as $role => $count) {
            User::factory($count)->create()->each(function (User $user) use ($role, &$usedNiks) {
                $user->assignRole($role);

                // `code` (buat invoicing/billing) cuma relevan untuk akun
                // customer — sama seperti UserService::create().
                if ($role === 'customer') {
                    $user->update([
                        'code' => 'CUS'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT),
                    ]);
                }

                $nik = $this->generateValidNik($usedNiks);
                $parsed = UserDetail::parseNik($nik);

                $user->userDetails()->create([
                    'nik' => $nik,
                    'birth_date' => $parsed['birth_date'],
                    'gender' => $parsed['gender'],
                ]);
            });
        }
    }

    /**
     * NIK bukan input bebas di NEXA — gender & birth_date selalu diturunkan
     * dari NIK lewat UserDetail::parseNik(). Generate sampai dapat NIK yang
     * lolos parsing (usia 17-100 tahun) dan belum dipakai user lain di batch
     * seeding ini.
     *
     * @param  array<int, string>  $usedNiks
     */
    private function generateValidNik(array &$usedNiks): string
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
        } while (UserDetail::parseNik($nik) === null || in_array($nik, $usedNiks, true));

        $usedNiks[] = $nik;

        return $nik;
    }
}
