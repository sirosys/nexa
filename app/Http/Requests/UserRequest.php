<?php

namespace App\Http\Requests;

use App\Rules\ValidNik;
use App\Support\PhoneNumber;
use App\Support\TitleCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public const ROLES = ['superadmin', 'technician', 'finance', 'sales', 'customer'];

    public function authorize(): bool
    {
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
        $userId = $this->route('user')?->id;

        // Semua kolom di form "Tambah Pengguna" wajib diisi — tapi cuma
        // saat create (POST). Saat edit (PUT/PATCH), NIK/foto KTP tetap
        // nullable: NIK terkunci begitu tersimpan (lihat guardNik() di
        // bawah) dan akun lama yang dibuat sebelum aturan wajib ini ada
        // harus tetap bisa diedit tanpa dipaksa melengkapi dulu.
        $isCreating = $this->isMethod('post');

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:9,15', Rule::unique('users', 'phone')->ignore($userId)],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', Rule::in(self::ROLES)],
            'nik' => [$isCreating ? 'required' : 'nullable', 'digits:16', new ValidNik, Rule::unique('user_details', 'nik')->ignore($userId, 'id')],
            'ktp_photo' => [$isCreating ? 'required' : 'nullable', 'image', 'max:4096'],
        ];
    }

    /**
     * Pesan validasi ditulis eksplisit di sini (bukan mengandalkan file
     * bahasa `lang/*`, yang belum ada untuk locale `id` di project ini —
     * itu sebabnya tanpa ini pesan yang muncul cuma nama key mentah, mis.
     * "validation.unique") — supaya gampang ditemukan & diubah satu tempat
     * kalau kata-katanya mau direvisi nanti, tanpa perlu menelusuri file
     * bahasa terpisah. Pola sama seperti `InstallationAssignRequest`/
     * `Auth\SendOtpRequest`.
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

            'role.required' => 'Role wajib dipilih.',
            'role.in' => 'Role yang dipilih tidak valid.',

            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus berupa 16 digit angka.',
            'nik.unique' => 'NIK ini sudah terdaftar untuk pengguna lain.',

            'ktp_photo.required' => 'Foto KTP wajib diunggah.',
            'ktp_photo.image' => 'File yang diunggah harus berupa gambar.',
            'ktp_photo.max' => 'Ukuran foto KTP maksimal 4 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = $this->route('user');

            $this->guardAgainstSelfDemotion($validator, $target);
            $this->guardNik($validator, $target);
        });
    }

    private function guardAgainstSelfDemotion(Validator $validator, mixed $target): void
    {
        $isSelf = $target && $target->id === $this->user()?->id;

        if ($isSelf && $target->hasRole('superadmin') && $this->input('role') !== 'superadmin') {
            $validator->errors()->add('role', 'Tidak bisa mengubah role akun sendiri dari Superadmin.');
        }
    }

    private function guardNik(Validator $validator, mixed $target): void
    {
        $existingNik = $target?->userDetails?->nik;
        $incomingNik = $this->input('nik');

        // NIK terkunci begitu pernah tersimpan — tidak bisa diubah lewat
        // form manapun (termasuk admin) untuk iterasi ini. Validitas NIK
        // itu sendiri (bisa di-parse atau tidak) sudah ditegakkan oleh
        // rule ValidNik di rules(), bukan di sini.
        if ($existingNik !== null && $incomingNik !== null && $incomingNik !== $existingNik) {
            $validator->errors()->add('nik', 'NIK sudah terkunci dan tidak bisa diubah.');
        }
    }
}
