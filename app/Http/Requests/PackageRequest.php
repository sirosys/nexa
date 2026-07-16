<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_starter' => ['sometimes', 'boolean'],
            'valid_until' => ['nullable', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            // Harga paket pendaftaran TIDAK PERNAH boleh gratis/0 (keputusan
            // bisnis eksplisit user 2026-07-16) — beda dari harga produk
            // pendukung individual (mis. biaya instalasi/modem) di bawah,
            // yang tetap boleh 0 untuk promo "gratis pasang & modem".
            'price' => ['required', 'numeric', 'gt:0'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'plan_qty' => ['required', 'integer', 'min:1'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->guardDuplicateProducts($validator);
        });
    }

    private function guardDuplicateProducts(Validator $validator): void
    {
        $productIds = collect($this->input('products', []))->pluck('product_id');

        if ($productIds->count() !== $productIds->unique()->count()) {
            $validator->errors()->add('products', 'Satu produk tidak boleh ditambahkan lebih dari sekali — gunakan kolom kuantitas.');
        }
    }
}
