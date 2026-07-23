<?php

namespace App\Models;

use Database\Factories\ServiceOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'service_id', 'package_id', 'plan_id', 'plan_price', 'plan_qty', 'is_starter', 'is_renewal', 'total', 'discount', 'subtotal', 'tax', 'admin_fee', 'grandtotal', 'notes', 'invoiced_at', 'expired_at', 'settled_at', 'canceled_at', 'renewal_reminder_h3_sent_at', 'renewal_reminder_h1_sent_at', 'created_by', 'updated_by'])]
class ServiceOrder extends Model
{
    /** @use HasFactory<ServiceOrderFactory> */
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
            'is_renewal' => 'boolean',
            'plan_price' => 'decimal:2',
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
            'renewal_reminder_h3_sent_at' => 'datetime',
            'renewal_reminder_h1_sent_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // `code` digenerate sebelum insert (independen dari id) — pola sama
        // Service::booted()/User::booted(), menggantikan cara lama
        // ('SAL'+6digit id) yang diisi belakangan lewat update() di
        // ServiceOrderService::create().
        static::creating(function (ServiceOrder $serviceOrder) {
            $serviceOrder->code ??= self::generateUniqueCode();
        });
    }

    /**
     * 16 karakter alphanumeric acak (alfabet tanpa I/O/0/1 supaya tidak
     * ambigu dibaca staff), dipakai sebagai `code` — juga jadi route key
     * (getRouteKeyName()) supaya URL /service-orders/{service_order}
     * memakai code, bukan id database. Panjang 16 (bukan 8 seperti
     * Service::generateUniqueCode()) supaya kode tidak terlihat sekuensial
     * meski volume transaksi masih kecil. `withTrashed()` karena
     * ServiceOrder pakai SoftDeletes — code tidak boleh dipakai ulang oleh
     * baris yang sudah soft-deleted.
     */
    public static function generateUniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 16; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::withTrashed()->where('code', $code)->exists());

        return $code;
    }

    /**
     * URL (mis. /service-orders/{service_order}) memakai `code`, bukan id
     * database — sengaja, supaya id tidak bisa dibaca/ditebak lewat URL.
     */
    public function getRouteKeyName(): string
    {
        return 'code';
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

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'service_order_products')
            ->withPivot(['price', 'discount', 'quantity', 'unit'])
            ->withTimestamps();
    }

    /** @return HasOne<Receipt, $this> */
    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }

    /** @return HasOne<ServiceActivation, $this> */
    public function activation(): HasOne
    {
        return $this->hasOne(ServiceActivation::class);
    }
}
