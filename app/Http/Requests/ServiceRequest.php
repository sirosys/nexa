<?php

namespace App\Http\Requests;

use App\Models\Package;
use App\Models\User;
use App\Support\TitleCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'address' => $this->filled('address') ? TitleCase::normalize((string) $this->input('address')) : $this->input('address'),
            'residential_name' => $this->filled('residential_name') ? TitleCase::normalize((string) $this->input('residential_name')) : $this->input('residential_name'),
        ]);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'address' => ['required', 'string'],
            'residential_name' => ['nullable', 'string', 'max:255'],
            'subdistrict_id' => ['required', 'integer', 'exists:subdistricts,id'],
            'rw' => ['nullable', 'string', 'max:10'],
            'rt' => ['nullable', 'string', 'max:10'],
            'coverage_id' => ['required', 'integer', Rule::exists('coverages', 'id')],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')],
            'pin' => [$this->isMethod('put') || $this->isMethod('patch') ? 'required' : 'nullable', 'digits:6'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userId = $this->input('user_id');

            if (! $userId) {
                return;
            }

            $user = User::with('userDetails')->find($userId);

            // Semua role (customer maupun staff) boleh didaftarkan Service —
            // keputusan eksplisit user 2026-07-23, lihat CLAUDE.md "Service".
            // Sebelumnya dibatasi harus role customer.

            // Gate ini cuma berlaku saat MENDAFTARKAN Service baru (POST),
            // bukan saat edit (PUT/PATCH) — kalau tidak, Service lama yang
            // user-nya belum lengkap (dibuat sebelum gate ini ada) jadi
            // tidak bisa diedit sama sekali. Pertahanan sisi server — UI
            // form Service sudah menggerbang ini lewat modal "Lengkapi NIK
            // & Foto KTP" saat create, tapi tetap ditegakkan ulang di sini
            // kalau ada yang submit request langsung tanpa lewat UI (lihat
            // CLAUDE.md "Service"). Berlaku untuk SIAPA PUN yang dipilih,
            // bukan cuma role customer — identitas KYC dibutuhkan terlepas
            // dari role user yang berlangganan.
            if ($this->isMethod('post') && $user && (blank($user->userDetails?->nik) || blank($user->userDetails?->ktp_photo))) {
                $validator->errors()->add('user_id', 'Pengguna ini belum melengkapi NIK & foto KTP.');
            }

            $packageId = $this->input('package_id');

            if ($packageId) {
                $package = Package::find($packageId);

                if ($package && ! $package->is_starter) {
                    $validator->errors()->add('package_id', 'Paket yang dipilih tidak tersedia untuk pendaftaran baru.');
                } elseif ($package && ! $package->isAvailable()) {
                    $validator->errors()->add('package_id', 'Paket yang dipilih sudah melewati masa berlaku.');
                }
            }
        });
    }
}
