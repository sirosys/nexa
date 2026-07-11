<?php

namespace App\Http\Requests;

use App\Rules\ValidNik;
use App\Support\PhoneNumber;
use App\Support\TitleCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public const ROLES = ['superadmin', 'technician', 'finance', 'sales', 'customer'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::normalize((string) $this->input('phone')),
            'name' => $this->filled('name') ? TitleCase::normalize((string) $this->input('name')) : $this->input('name'),
        ]);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:9,15', Rule::unique('users', 'phone')->ignore($userId)],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'role' => ['required', Rule::in(self::ROLES)],
            'nik' => ['nullable', 'digits:16', new ValidNik, Rule::unique('user_details', 'nik')->ignore($userId, 'id')],
            'ktp_photo' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $target = $this->route('user');

            $this->guardAgainstSelfDemotion($validator, $target);
            $this->guardNik($validator, $target);
        });
    }

    private function guardAgainstSelfDemotion(Validator $validator, mixed $target): void
    {
        $isSelf = $target && $target->id === $this->user()?->id;

        if ($isSelf && $target->hasRole('superadmin') && $this->input('role') !== 'superadmin') {
            $validator->errors()->add('role', 'Tidak bisa mengubah role akun sendiri dari Superadmin.');
        }
    }

    private function guardNik(Validator $validator, mixed $target): void
    {
        $existingNik = $target?->userDetails?->nik;
        $incomingNik = $this->input('nik');

        // NIK terkunci begitu pernah tersimpan — tidak bisa diubah lewat
        // form manapun (termasuk admin) untuk iterasi ini. Validitas NIK
        // itu sendiri (bisa di-parse atau tidak) sudah ditegakkan oleh
        // rule ValidNik di rules(), bukan di sini.
        if ($existingNik !== null && $incomingNik !== null && $incomingNik !== $existingNik) {
            $validator->errors()->add('nik', 'NIK sudah terkunci dan tidak bisa diubah.');
        }
    }
}
