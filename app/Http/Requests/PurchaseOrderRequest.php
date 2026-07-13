<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.inventory_item_id' => ['required', 'integer', 'exists:inventory_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->guardDuplicateItems($validator);
        });
    }

    private function guardDuplicateItems(Validator $validator): void
    {
        $itemIds = collect($this->input('items', []))->pluck('inventory_item_id');

        if ($itemIds->count() !== $itemIds->unique()->count()) {
            $validator->errors()->add('items', 'Satu item inventaris tidak boleh ditambahkan lebih dari sekali — gunakan kolom kuantitas.');
        }
    }
}
