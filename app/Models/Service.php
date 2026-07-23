<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'pin', 'user_id', 'address', 'residential_name', 'subdistrict_id', 'rw', 'rt', 'coverage_id', 'package_id', 'status', 'activated_at', 'expired_at', 'suspended_at', 'dismantled_at', 'canceled_at', 'created_by', 'updated_by'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, SoftDeletes;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PENDING_INSTALLATION = 'pending_installation';

    public const STATUS_INSTALLING = 'installing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_CANCELED = 'canceled';

    // Modul Dismantle — lihat CLAUDE.md "Dismantle".
    public const STATUS_PENDING_DISMANTLE = 'pending_dismantle';

    public const STATUS_DISMANTLING = 'dismantling';

    public const STATUS_DISMANTLED = 'dismantled';

    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PENDING_INSTALLATION,
        self::STATUS_INSTALLING,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_CANCELED,
        self::STATUS_PENDING_DISMANTLE,
        self::STATUS_DISMANTLING,
        self::STATUS_DISMANTLED,
    ];

    // Label Bahasa Indonesia per status — dipindah ke sini dari duplikasi
    // inline di services/index.blade.php & services/show.blade.php begitu
    // modul Dashboard butuh label yang sama untuk chart distribusi status
    // (lihat CLAUDE.md "Dashboard"). View lama belum di-retrofit memakai ini
    // (di luar scope), tapi modul baru sebaiknya reuse konstanta ini.
    public const STATUS_LABELS = [
        self::STATUS_PENDING_PAYMENT => 'Menunggu Pembayaran',
        self::STATUS_PENDING_INSTALLATION => 'Menunggu Instalasi',
        self::STATUS_INSTALLING => 'Sedang Instalasi',
        self::STATUS_ACTIVE => 'Aktif',
        self::STATUS_SUSPENDED => 'Suspend',
        self::STATUS_CANCELED => 'Dibatalkan',
        self::STATUS_PENDING_DISMANTLE => 'Antre Dismantle',
        self::STATUS_DISMANTLING => 'Sedang Dismantle',
        self::STATUS_DISMANTLED => 'Dibongkar',
    ];

    protected static function booted(): void
    {
        // `code` digenerate sebelum insert (independen dari id) — pola sama
        // User::booted(), direplikasi dari logic ~/Webapp/xnet/app (lihat
        // Service::generateUniqueCode() di bawah), bukan lagi turunan id
        // ('SRV'+6digit) yang diisi belakangan lewat update() di
        // ServiceService::create().
        static::creating(function (Service $service) {
            $service->code ??= self::generateUniqueCode();
        });
    }

    /**
     * 8 karakter alphanumeric acak (alfabet tanpa I/O/0/1 supaya tidak
     * ambigu dibaca staff/teknisi di lapangan), dipakai sebagai `code` —
     * juga jadi route key (getRouteKeyName()) supaya URL /services/{service}
     * (dan Installation/Dismantle yang route-model-binding di atas Service
     * yang sama) memakai code, bukan id database. `withTrashed()` karena
     * Service pakai SoftDeletes — code tidak boleh dipakai ulang oleh baris
     * yang sudah soft-deleted.
     */
    public static function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /**
     * URL (mis. /services/{service}, /installations/{service},
     * /dismantles/{service}) memakai `code`, bukan id database — sengaja,
     * supaya id tidak bisa dibaca/ditebak lewat URL. Pola sama
     * User::getRouteKeyName().
     */
    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activated_at' => 'datetime',
            'expired_at' => 'datetime',
            'suspended_at' => 'datetime',
            'dismantled_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Subdistrict, $this> */
    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class);
    }

    /** @return BelongsTo<Coverage, $this> */
    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class);
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** @return HasMany<ServiceOrder, $this> */
    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    /** @return HasOne<ServiceActivation, $this> */
    public function activation(): HasOne
    {
        return $this->hasOne(ServiceActivation::class);
    }

    /** @return HasOne<ServiceDismantle, $this> */
    public function dismantle(): HasOne
    {
        return $this->hasOne(ServiceDismantle::class);
    }
}
