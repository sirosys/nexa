<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ServiceOrder;
use App\Support\ServiceOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ServiceOrder $this */
        $status = ServiceOrderStatus::resolve($this->resource);

        return [
            'code' => $this->code,
            'status' => $status['key'],
            'status_label' => $status['label'],
            'is_starter' => (bool) $this->is_starter,
            'is_renewal' => (bool) $this->is_renewal,
            'total' => (float) $this->total,
            'discount' => (float) $this->discount,
            'subtotal' => (float) $this->subtotal,
            'tax' => (float) $this->tax,
            'admin_fee' => (float) $this->admin_fee,
            'grandtotal' => (float) $this->grandtotal,
            'invoiced_at' => $this->invoiced_at?->toIso8601String(),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'settled_at' => $this->settled_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),
            // Link signed sudah final sejak
            // ReceiptService::createForServiceOrder(), TIDAK PERNAH
            // diregenerate di sini (lihat CLAUDE.md "Billing / Invoice
            // (Xendit)") — null kalau belum ada Receipt (mis. Order Layanan
            // gratis, auto-settled tanpa Receipt sama sekali).
            'checkout_url' => $this->receipt?->checkout_url,
        ];
    }
}
