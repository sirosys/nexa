<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DismantleAssignRequest extends FormRequest
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
            'technician_id' => ['required', Rule::in(User::role('technician')->pluck('id'))],
        ];
    }

    public function messages(): array
    {
        return [
            'technician_id.required' => 'Pilih teknisi terlebih dahulu.',
            'technician_id.in' => 'Teknisi tidak valid.',
        ];
    }
}
