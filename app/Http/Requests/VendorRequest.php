<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Nama vendor SENGAJA tidak dinormalisasi TitleCase (beda dari
            // User/Service) — nama perusahaan (mis. "PT Maju Jaya", "CV
            // ABC") gampang rusak kalau dipaksa Str::title(), biarkan apa
            // adanya sesuai input staff.
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
