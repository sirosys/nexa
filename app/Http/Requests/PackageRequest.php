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
