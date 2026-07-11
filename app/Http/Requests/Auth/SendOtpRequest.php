<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendOtpRequest extends FormRequest
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
            'phone' => ['required', 'digits_between:9,15', Rule::exists('users', 'phone')],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.exists' => 'Nomor telepon tidak terdaftar.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // NEXA khusus admin/staff — aplikasi pelanggan terpisah belum
        // dibangun (lihat CLAUDE.md "Authentication / Login"). Dicek di
        // sini (bukan cuma exists) supaya customer ditolak dengan pesan
        // yang jelas sebelum OTP sempat dikirim, bukan setelah verifikasi.
        $validator->after(function (Validator $validator) {
            $phone = $this->input('phone');
            $user = $phone ? User::where('phone', $phone)->first() : null;

            if ($user?->isCustomer()) {
                $validator->errors()->add('phone', 'Nomor ini terdaftar sebagai pelanggan. Aplikasi ini khusus untuk admin dan staff.');
            }
        });
    }
}
