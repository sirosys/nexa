<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceTicketResolveRequest extends FormRequest
{
    // Otorisasi ditulis eksplisit di ServiceTicketController (bukan action
    // resource standar) — lihat CLAUDE.md "Ticketing".
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
            'resolution_notes' => ['nullable', 'string'],
        ];
    }
}
