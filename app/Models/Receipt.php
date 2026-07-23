<?php

namespace App\Models;

use Database\Factories\ReceiptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'service_order_id', 'xendit_payment_request_id', 'channel_code', 'amount', 'status', 'checkout_url', 'raw_response', 'created_by', 'updated_by'])]
class Receipt extends Model
{
    /** @use HasFactory<ReceiptFactory> */
    use HasFactory;

    /**
     * Status sentinel NEXA - BUKAN status asli Xendit (lihat CLAUDE.md
     * "Billing / Invoice (Xendit)"). Dipakai selama pelanggan belum
     * memilih channel di halaman /pay/{receipt}; begitu channel dipilih
     * dan Payment Request Xendit berhasil dibuat, status diganti nilai
     * mentah dari Xendit (PENDING/REQUIRES_ACTION/dst).
     */
    public const STATUS_AWAITING_CHANNEL_SELECTION = 'AWAITING_CHANNEL_SELECTION';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'raw_response' => 'array',
        ];
    }

    /** @return BelongsTo<ServiceOrder, $this> */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /** @return HasMany<ReceiptOtpCode, $this> */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(ReceiptOtpCode::class);
    }
}
