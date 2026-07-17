<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Langkah 1 registrasi mandiri — verifikasi kepemilikan nomor SEBELUM data
 * lain (nama/email) dikumpulkan. `Rule::unique` (kebalikan `SendOtpRequest`/
 * `SendCustomerOtpRequest` yang `Rule::exists`) — nomor yang mau daftar
 * justru HARUS belum terdaftar sama sekali, baik sebagai customer maupun
 * staff (lihat CLAUDE.md "API Customer-Facing").
 */
class RegisterRequestOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::normalize((string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'digits_between:9,15', Rule::unique('users', 'phone')],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.digits_between' => 'Format nomor telepon tidak valid.',
            'phone.unique' => 'Nomor telepon ini sudah terdaftar. Silakan masuk lewat menu login.',
        ];
    }
}
