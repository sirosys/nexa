<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InstallationCompleteRequest extends FormRequest
{
    // Otorisasi ditulis eksplisit di InstallationController (bukan action
    // resource standar) — lihat CLAUDE.md "Installation".
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'odp_port' => ['required', 'string', 'max:20'],
            'cable_length' => ['nullable', 'numeric', 'min:0'],
            'photo' => ['required', 'image', 'max:4096'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'odp_port.required' => 'Nomor port ODP wajib diisi.',
            'photo.required' => 'Foto bukti instalasi wajib diunggah.',
            'photo.image' => 'Foto bukti instalasi harus berupa gambar.',
        ];
    }
}
