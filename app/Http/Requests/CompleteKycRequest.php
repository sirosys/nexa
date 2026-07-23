<?php

namespace App\Http\Requests;

use App\Rules\ValidNik;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dipakai oleh modal "Lengkapi NIK & Foto KTP" di form Service — gate
 * wajib sebelum staff bisa mendaftarkan Service untuk pelanggan yang
 * belum lengkap datanya (lihat CLAUDE.md "Service"). Beda dari
 * UserRequest: nik & ktp_photo di sini WAJIB (bukan nullable), karena
 * endpoint ini memang cuma dipanggil untuk melengkapi keduanya sekaligus.
 */
class CompleteKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Pola konsisten dengan action non-resource lain (mis.
        // ServiceOrderController::retryReceipt()) — otorisasi ditulis eksplisit
        // di controller (UserController::completeKyc()), bukan di sini.
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'nik' => ['required', 'digits:16', new ValidNik, Rule::unique('user_details', 'nik')->ignore($userId, 'id')],
            'ktp_photo' => ['required', 'image', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = $this->route('user');

            if ($target?->userDetails?->nik !== null) {
                $validator->errors()->add('nik', 'NIK sudah pernah diisi sebelumnya.');
            }
        });
    }
}
