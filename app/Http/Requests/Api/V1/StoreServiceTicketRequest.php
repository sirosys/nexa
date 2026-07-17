<?php

namespace App\Http\Requests\Api\V1;

use App\Models\ServiceTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bukan reuse App\Http\Requests\ServiceTicketRequest (admin) — request itu
 * menerima `service_id` bebas di body, sedangkan di sini Service SELALU
 * dari route yang sudah di-scope ke user login (lihat CLAUDE.md "API
 * Customer-Facing"), pelanggan tidak boleh submit `service_id` sendiri.
 */
class StoreServiceTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(ServiceTicket::CATEGORIES)],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Kategori tiket wajib diisi.',
            'category.in' => 'Kategori tiket tidak valid.',
            'subject.required' => 'Judul tiket wajib diisi.',
            'description.required' => 'Deskripsi tiket wajib diisi.',
        ];
    }
}
