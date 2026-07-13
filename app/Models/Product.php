<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['code', 'type', 'name', 'description', 'price', 'unit', 'created_by', 'updated_by'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    /** @return BelongsToMany<Package, $this> */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class)
            ->withPivot(['quantity', 'price'])
            ->withTimestamps();
    }

    /** @return HasOne<InventoryItem, $this> */
    public function inventoryItem(): HasOne
    {
        return $this->hasOne(InventoryItem::class);
    }
}
