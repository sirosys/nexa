<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Rule::exists query langsung ke tabel, tidak menghormati global
            // scope SoftDeletes milik model Service — whereNull('deleted_at')
            // wajib eksplisit supaya service yang sudah soft-deleted ditolak.
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'package_id' => ['required', 'integer', 'exists:packages,id'],
            'notes' => ['nullable', 'string'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'admin_fee' => ['nullable', 'numeric', 'min:0'],
            'products' => ['required', 'array', 'min:1'],
            'products.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'products.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
            'products.*.discount' => ['nullable', 'numeric', 'min:0'],
            'products.*.unit' => ['nullable', 'string', 'max:50'],
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
