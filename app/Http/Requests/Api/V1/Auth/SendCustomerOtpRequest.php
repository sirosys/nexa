<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCustomerOtpRequest extends FormRequest
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
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.digits_between' => 'Format nomor telepon tidak valid.',
            'phone.exists' => 'Nomor telepon tidak terdaftar.',
        ];
    }

    /**
     * Guard kebalikan dari SendOtpRequest (admin) — di sini yang DITOLAK
     * justru nomor yang BUKAN customer, karena API ini khusus aplikasi
     * pelanggan (lihat CLAUDE.md "Authentication / Login" &
     * "API Customer-Facing").
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $phone = $this->input('phone');
            $user = $phone ? User::where('phone', $phone)->first() : null;

            if ($user && ! $user->isCustomer()) {
                $validator->errors()->add('phone', 'Nomor ini bukan nomor pelanggan.');
            }
        });
    }
}
