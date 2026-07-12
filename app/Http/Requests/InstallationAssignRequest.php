<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstallationAssignRequest extends FormRequest
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
            'installer_id' => ['required', Rule::in(User::role('technician')->pluck('id'))],
        ];
    }

    public function messages(): array
    {
        return [
            'installer_id.required' => 'Pilih teknisi terlebih dahulu.',
            'installer_id.in' => 'Teknisi tidak valid.',
        ];
    }
}
