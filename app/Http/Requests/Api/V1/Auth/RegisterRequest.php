<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\TitleCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Langkah 2 registrasi mandiri — `registration_token`+`code` membuktikan
 * kepemilikan nomor (lihat RegisterRequestOtpRequest & RegistrationOtpService),
 * nomor telepon SENGAJA tidak diminta ulang di sini (selalu diambil dari
 * challenge yang sudah terverifikasi, bukan dari body — mencegah submit
 * nomor yang berbeda dari yang baru saja diverifikasi). "Daftar ringan
 * dulu" (lihat CLAUDE.md "API Customer-Facing") — TANPA nik/ktp_photo,
 * beda dari QuickCreateCustomerRequest (admin) yang mewajibkan keduanya.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? TitleCase::normalize((string) $this->input('name')) : $this->input('name'),
        ]);
    }

    public function rules(): array
    {
        return [
            'registration_token' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
        ];
    }

    public function messages(): array
    {
        return [
            'registration_token.required' => 'Token verifikasi wajib diisi.',
            'code.required' => 'Kode OTP wajib diisi.',
            'code.digits' => 'Kode OTP harus 6 digit.',
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 255 karakter.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar untuk pengguna lain.',
        ];
    }
}
