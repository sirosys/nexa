<?php

namespace App\Http\Requests;

use App\Rules\ValidNik;
use App\Support\PhoneNumber;
use App\Support\TitleCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dipakai oleh modal "Tambah Pelanggan Baru" di form Service (lihat
 * CLAUDE.md "Service") — subset dari UserRequest (role selalu dipaksa
 * `customer`, tidak ada input role di modal ini). NIK & foto KTP WAJIB
 * diisi di sini juga (sejak 2026-07-16, disamakan dengan form
 * "Tambah Pengguna" di /users) — tidak ada lagi modal "Lengkapi NIK &
 * Foto KTP" susulan untuk jalur "pelanggan baru", satu submission
 * langsung lengkap. Modal KYC terpisah (lihat CompleteKycRequest) tetap
 * dipakai untuk kasus lain: pelanggan LAMA yang ditemukan lewat
 * pencarian tapi datanya belum lengkap.
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
            'nik' => ['required', 'digits:16', new ValidNik, Rule::unique('user_details', 'nik')],
            'ktp_photo' => ['required', 'image', 'max:4096'],
        ];
    }

    /**
     * Pesan Bahasa Indonesia eksplisit — pola sama `UserRequest::messages()`,
     * lihat CLAUDE.md "User" untuk alasan (project ini tidak punya file
     * bahasa `lang/id/*`, tanpa ini pesan yang muncul cuma nama key mentah).
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama wajib diisi.',
            'name.max' => 'Nama maksimal 255 karakter.',

            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.digits_between' => 'Nomor telepon harus 9-15 digit angka.',
            'phone.unique' => 'Nomor telepon ini sudah terdaftar untuk pengguna lain.',

            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.max' => 'Email maksimal 255 karakter.',
            'email.unique' => 'Email ini sudah terdaftar untuk pengguna lain.',

            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus berupa 16 digit angka.',
            'nik.unique' => 'NIK ini sudah terdaftar untuk pengguna lain.',

            'ktp_photo.required' => 'Foto KTP wajib diunggah.',
            'ktp_photo.image' => 'File yang diunggah harus berupa gambar.',
            'ktp_photo.max' => 'Ukuran foto KTP maksimal 4 MB.',
        ];
    }
}
