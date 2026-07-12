<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DismantleCompleteRequest extends FormRequest
{
    // Otorisasi ditulis eksplisit di DismantleController (bukan action
    // resource standar) — lihat CLAUDE.md "Dismantle".
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
            'photo' => ['required', 'image', 'max:4096'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Foto bukti pembongkaran wajib diunggah.',
            'photo.image' => 'Foto bukti pembongkaran harus berupa gambar.',
        ];
    }
}
