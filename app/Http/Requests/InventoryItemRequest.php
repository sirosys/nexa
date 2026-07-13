<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class InventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id', 'unique:inventory_items,product_id'],
            'is_serialized' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->guardProductIsPerangkat($validator);
        });
    }

    // Inventaris cuma masuk akal untuk produk fisik — pola sama guard
    // is_starter di ServiceRequest.
    private function guardProductIsPerangkat(Validator $validator): void
    {
        $productId = $this->input('product_id');

        if (blank($productId)) {
            return;
        }

        $product = Product::find($productId);

        if ($product !== null && $product->type !== 'perangkat') {
            $validator->errors()->add('product_id', 'Hanya produk bertipe "Perangkat" yang bisa dijadikan item inventaris.');
        }
    }
}
