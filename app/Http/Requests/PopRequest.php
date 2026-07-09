<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'subdistrict_id' => ['required', 'integer', 'exists:subdistricts,id'],
            'serial' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
        ];
    }
}
