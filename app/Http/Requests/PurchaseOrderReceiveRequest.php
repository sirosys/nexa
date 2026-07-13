<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderReceiveRequest extends FormRequest
{
    // Otorisasi ditulis eksplisit di PurchaseOrderController (bukan action
    // resource standar) — pola sama Installation/Dismantle/Inventaris.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Keyed by inventory_item_id — cuma dibutuhkan untuk baris
            // item yang is_serialized=true, satu baris per unit dipisah
            // baris baru (pola sama InventoryStockInRequest::serial_numbers).
            // Guard "jumlah serial harus sama dengan quantity dipesan" ada
            // di PurchaseOrderService::receive() (butuh baca baris PO
            // spesifik, bukan sekadar bentuk request).
            'serial_numbers' => ['nullable', 'array'],
            'serial_numbers.*' => ['nullable', 'string'],
        ];
    }
}
