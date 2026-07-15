<?php

namespace App\Http\Requests;

use App\Models\Product;
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'plan_price' => ['required', 'numeric', 'min:0'],
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
            $this->guardNoLegacySubscriptionProducts($validator);
        });
    }

    private function guardDuplicateProducts(Validator $validator): void
    {
        $productIds = collect($this->input('products', []))->pluck('product_id');

        if ($productIds->count() !== $productIds->unique()->count()) {
            $validator->errors()->add('products', 'Satu produk tidak boleh ditambahkan lebih dari sekali — gunakan kolom kuantitas.');
        }
    }

    /**
     * Produk bertipe 'langganan' sudah digantikan Plan (lihat CLAUDE.md
     * "Plan") — tolak kalau staff masih mencoba membundel produk jenis itu
     * ke package_product, supaya kebingungan lama (base product numpang di
     * katalog produk umum) tidak terulang untuk paket baru.
     */
    private function guardNoLegacySubscriptionProducts(Validator $validator): void
    {
        $productIds = collect($this->input('products', []))->pluck('product_id')->filter();

        if ($productIds->isEmpty()) {
            return;
        }

        $hasLegacySubscription = Product::whereIn('id', $productIds)
            ->where('type', 'langganan')
            ->exists();

        if ($hasLegacySubscription) {
            $validator->errors()->add('products', 'Produk bertipe Langganan sudah digantikan Plan — pilih dari Plan di atas, bukan di sini.');
        }
    }
}
