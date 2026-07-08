<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Data pribadi (KYC) untuk sembarang akun `users` — customer, staff, teknisi,
 * maupun admin. Relasinya didefinisikan di `User::userDetails()`.
 */
#[Fillable(['nik', 'birth_date', 'gender', 'ktp_photo'])]
class UserDetail extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'birth_date' => 'date',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id', 'id');
    }

    /**
     * Uraikan NIK (16 digit) jadi gender + tanggal lahir berdasarkan struktur
     * NIK Indonesia (digit 7-8 hari, 9-10 bulan, 11-12 tahun 2 digit; hari
     * +40 menandakan perempuan). Abad tahun lahir ditentukan dari usia yang
     * masuk akal (17-100 tahun) relatif terhadap tanggal saat ini.
     *
     * @return array{gender: string, birth_date: string}|null
     */
    public static function parseNik(string $nik): ?array
    {
        if (! preg_match('/^\d{16}$/', $nik)) {
            return null;
        }

        $dayDigits = (int) substr($nik, 6, 2);
        $month = (int) substr($nik, 8, 2);
        $yearTwoDigits = (int) substr($nik, 10, 2);

        $gender = 'male';
        $day = $dayDigits;

        if ($dayDigits > 40) {
            $gender = 'female';
            $day = $dayDigits - 40;
        }

        $baseCentury = intdiv((int) now()->format('Y'), 100) * 100;

        foreach ([$baseCentury, $baseCentury - 100] as $century) {
            $year = $century + $yearTwoDigits;

            if (! checkdate($month, $day, $year)) {
                continue;
            }

            $birthDate = Carbon::createFromDate($year, $month, $day)->startOfDay();
            $age = $birthDate->diffInYears(now());

            if ($age >= 17 && $age <= 100) {
                return [
                    'gender' => $gender,
                    'birth_date' => $birthDate->format('Y-m-d'),
                ];
            }
        }

        return null;
    }
}
