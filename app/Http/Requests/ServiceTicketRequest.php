<?php

namespace App\Http\Requests;

use App\Models\ServiceTicket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceTicketRequest extends FormRequest
{
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
            // Rule::exists query langsung ke tabel, tidak menghormati global
            // scope SoftDeletes milik model Service — whereNull('deleted_at')
            // wajib eksplisit, pola sama SaleRequest.
            'service_id' => ['required', 'integer', Rule::exists('services', 'id')->whereNull('deleted_at')],
            'category' => ['required', Rule::in(ServiceTicket::CATEGORIES)],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
        ];
    }
}
