<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'is_starter', 'valid_until', 'plan_id', 'plan_price', 'plan_qty', 'name', 'description', 'price', 'created_by', 'updated_by'])]
class Package extends Model
{
    /** @use HasFactory<PackageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_starter' => 'boolean',
            'valid_until' => 'datetime',
            'price' => 'decimal:2',
            'plan_price' => 'decimal:2',
        ];
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->withPivot(['quantity', 'price'])
            ->withTimestamps();
    }

    /**
     * Paket masih boleh ditawarkan untuk dipilih — null berarti unlimited
     * (selalu tersedia), terisi berarti tidak lagi tersedia begitu tanggal
     * ini lewat (mis. promo 1-2 bulan). Lihat CLAUDE.md "Product & Package".
     */
    public function isAvailable(): bool
    {
        return $this->valid_until === null || $this->valid_until->isFuture();
    }

    /** @param  Builder<Package>  $query */
    public function scopeAvailable(Builder $query): void
    {
        $query->where(function (Builder $query) {
            $query->whereNull('valid_until')->orWhere('valid_until', '>', now());
        });
    }

    /**
     * Plan internet (tier layanan) yang mewakili paket ini — dipakai
     * RenewalService untuk menagih perpanjangan (lihat CLAUDE.md "Renewal"
     * dan "Plan").
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
