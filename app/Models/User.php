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
}
