<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_item_id', 'serial_number', 'status', 'service_id', 'created_by', 'updated_by'])]
class InventoryUnit extends Model
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_INSTALLED = 'installed';

    public const STATUSES = [
        self::STATUS_IN_STOCK,
        self::STATUS_INSTALLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_IN_STOCK => 'Di Gudang',
        self::STATUS_INSTALLED => 'Terpasang',
    ];

    /** @return BelongsTo<InventoryItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
