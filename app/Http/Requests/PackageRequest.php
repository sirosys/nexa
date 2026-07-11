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
            $this->guardSubscriptionDuration($validator);
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
     * Quantity semua item produk bertipe 'langganan' dalam satu paket wajib
     * seragam — durasi masa aktif paket diturunkan dari quantity item ini
     * (lihat PackageService::deriveDurationMonths()), jadi nilai yang
     * berbeda-beda akan ambigu bulan mana yang dipakai.
     */
    private function guardSubscriptionDuration(Validator $validator): void
    {
        $products = collect($this->input('products', []));
        $productIds = $products->pluck('product_id')->filter();

        if ($productIds->isEmpty()) {
            return;
        }

        $subscriptionProductIds = Product::whereIn('id', $productIds)
            ->where('type', 'langganan')
            ->pluck('id');

        $quantities = $products
            ->filter(fn (array $row) => $subscriptionProductIds->contains($row['product_id'] ?? null))
            ->pluck('quantity')
            ->unique();

        if ($quantities->count() > 1) {
            $validator->errors()->add('products', 'Jumlah kuantitas semua produk bertipe langganan dalam satu paket harus sama.');
        }
    }
}
