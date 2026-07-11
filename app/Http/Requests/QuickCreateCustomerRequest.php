<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use App\Support\TitleCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dipakai oleh modal "Tambah Pelanggan Baru" di form Service (lihat
 * CLAUDE.md "Service") — subset dari UserRequest, role selalu dipaksa
 * `customer` (tidak ada input role di modal ini), dan NIK/foto KTP
 * sengaja tidak diminta di sini karena langsung disusul modal "Lengkapi
 * NIK & Foto KTP" (endpoint terpisah, lihat CompleteKycRequest).
 */
class QuickCreateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi ditulis eksplisit di ServiceController::storeCustomer(),
        // pola sama seperti action non-resource lain (mis. searchCustomers()).
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::normalize((string) $this->input('phone')),
            'name' => $this->filled('name') ? TitleCase::normalize((string) $this->input('name')) : $this->input('name'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:9,15', Rule::unique('users', 'phone')],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
        ];
    }
}
