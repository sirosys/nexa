<?php

namespace App\Models;

use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'service_id', 'package_id', 'is_starter', 'total', 'discount', 'subtotal', 'tax', 'admin_fee', 'grandtotal', 'notes', 'invoiced_at', 'expired_at', 'settled_at', 'canceled_at', 'created_by', 'updated_by'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_starter' => 'boolean',
            'total' => 'decimal:2',
            'discount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'grandtotal' => 'decimal:2',
            'invoiced_at' => 'datetime',
            'expired_at' => 'datetime',
            'settled_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<Package, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'sale_products')
            ->withPivot(['price', 'discount', 'quantity', 'unit'])
            ->withTimestamps();
    }

    /** @return HasOne<Receipt, $this> */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}
