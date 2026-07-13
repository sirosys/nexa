<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
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
            // Boleh negatif (mis. kehilangan/rusak) — tidak boleh 0.
            'delta' => ['required', 'integer', 'not_in:0'],
            'notes' => ['required', 'string'],
        ];
    }
}
