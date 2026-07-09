<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CoverageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pop_id' => ['required', 'integer', 'exists:pops,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ];
    }
}
