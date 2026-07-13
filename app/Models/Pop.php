<?php

namespace App\Models;

use Database\Factories\PopFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'subdistrict_id', 'serial', 'model', 'location', 'token', 'host', 'api_port', 'api_username', 'last_online_at', 'status', 'created_by', 'updated_by'])]
class Pop extends Model
{
    /** @use HasFactory<PopFactory> */
    use HasFactory;

    protected $hidden = ['token'];

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUS_ONLINE = 'online';

    public const STATUS_OFFLINE = 'offline';

    public const STATUSES = [
        self::STATUS_UNKNOWN,
        self::STATUS_ONLINE,
        self::STATUS_OFFLINE,
    ];

    public const STATUS_LABELS = [
        self::STATUS_UNKNOWN => 'Belum Diketahui',
        self::STATUS_ONLINE => 'Online',
        self::STATUS_OFFLINE => 'Offline',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'last_online_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Subdistrict, $this> */
    public function subdistrict(): BelongsTo
    {
        return $this->belongsTo(Subdistrict::class);
    }

    /** @return HasMany<Coverage, $this> */
    public function coverages(): HasMany
    {
        return $this->hasMany(Coverage::class);
    }
}
