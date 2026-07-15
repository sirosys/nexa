<?php

namespace App\Models;

use Database\Factories\PackageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'is_starter', 'plan_id', 'plan_price', 'plan_qty', 'name', 'description', 'price', 'created_by', 'updated_by'])]
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
