<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\ValidNik;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Versi API dari App\Http\Requests\CompleteKycRequest (admin) — duplikasi
 * disengaja (bukan sharing trait, tidak ada presedennya di project ini,
 * lihat StoreServiceTicketRequest yang juga duplikasi rules dari request
 * admin-nya, bukan sharing). Beda satu-satunya: target user SELALU
 * $this->user() (identitas Sanctum yang login), bukan $this->route('user')
 * — tidak ada route-model-binding di /api/v1 (lihat CLAUDE.md "API
 * Customer-Facing").
 */
class CompleteOwnKycRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nik' => ['required', 'digits:16', new ValidNik, Rule::unique('user_details', 'nik')->ignore($this->user()->id, 'id')],
            'ktp_photo' => ['required', 'image', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->user()->userDetails?->nik !== null) {
                $validator->errors()->add('nik', 'NIK sudah pernah diisi sebelumnya.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus 16 digit.',
            'ktp_photo.required' => 'Foto KTP wajib diunggah.',
            'ktp_photo.image' => 'Foto KTP harus berupa gambar.',
            'ktp_photo.max' => 'Ukuran foto KTP maksimal 4MB.',
        ];
    }
}
