<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'code', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'phone' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // `code` sekarang wajib untuk SEMUA role (bukan cuma customer,
        // lihat CLAUDE.md "User") — dijamin di sini (bukan cuma di
        // UserService) supaya SETIAP jalur pembuatan user (factory, seeder,
        // tinker) otomatis dapat code, tanpa perlu diulang tiap pemanggil.
        // Random (bukan turunan id) sehingga bisa digenerate sebelum insert.
        static::creating(function (User $user) {
            $user->code ??= self::generateUniqueCode();
        });
    }

    /**
     * 6 karakter alphanumeric acak, dipakai sebagai `code` (lihat
     * booted() di atas) — juga jadi route key (getRouteKeyName()) supaya
     * URL /users/{user} tidak membocorkan id database.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = Str::upper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    /**
     * URL (mis. /users/{user}, /secure/ktp/{user}) memakai `code`, bukan id
     * database — sengaja, supaya id tidak bisa dibaca/ditebak lewat URL.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * NEXA (aplikasi ini) khusus untuk admin/staff — customer akan punya
     * aplikasi terpisah yang belum dibangun (lihat CLAUDE.md "Authentication
     * / Login"). Dipakai untuk menolak login customer di alur OTP.
     */
    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }

    public function isTechnician(): bool
    {
        return $this->hasRole('technician');
    }

    /** @return HasMany<OtpCode, $this> */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class);
    }

    /** @return HasOne<UserDetail, $this> */
    public function userDetails(): HasOne
    {
        return $this->hasOne(UserDetail::class, 'id', 'id');
    }

    /**
     * Layanan yang didaftarkan atas nama user ini — hanya bermakna untuk
     * role `customer` (lihat CLAUDE.md "Service"), tapi relasi ini generik
     * di `User` karena FK-nya memang ke `users.id` langsung.
     *
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }
}
