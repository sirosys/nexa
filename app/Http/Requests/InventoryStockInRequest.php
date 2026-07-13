<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryStockInRequest extends FormRequest
{
    // Otorisasi ditulis eksplisit di InventoryItemController (bukan action
    // resource standar) — lihat CLAUDE.md "Inventaris".
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Cuma dipakai kalau item non-serialized.
            'quantity' => ['required_without:serial_numbers', 'nullable', 'integer', 'min:1'],
            // Cuma dipakai kalau item serialized — satu baris per unit
            // baru, dipisah baris baru dari textarea di form.
            'serial_numbers' => ['required_without:quantity', 'nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
