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

    /** @return HasMany<Sale, $this> */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
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
