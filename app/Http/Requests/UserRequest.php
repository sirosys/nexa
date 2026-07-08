<?php

namespace App\Http\Requests;

use App\Models\UserDetail;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => PhoneNumber::normalize((string) $this->input('phone')),
            'admin' => $this->boolean('admin'),
        ]);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'digits_between:9,15', Rule::unique('users', 'phone')->ignore($userId)],
            'admin' => ['boolean'],
            'nik' => ['nullable', 'digits:16', Rule::unique('user_details', 'nik')->ignore($userId, 'id')],
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

        if ($isSelf && $target->admin && ! $this->boolean('admin')) {
            $validator->errors()->add('admin', 'Tidak bisa mencabut status admin dari akun sendiri.');
        }
    }

    private function guardNik(Validator $validator, mixed $target): void
    {
        $existingNik = $target?->userDetails?->nik;
        $incomingNik = $this->input('nik');

        // NIK terkunci begitu pernah tersimpan — tidak bisa diubah lewat
        // form manapun (termasuk admin) untuk iterasi ini.
        if ($existingNik !== null) {
            if ($incomingNik !== null && $incomingNik !== $existingNik) {
                $validator->errors()->add('nik', 'NIK sudah terkunci dan tidak bisa diubah.');
            }

            return;
        }

        if ($incomingNik !== null && UserDetail::parseNik($incomingNik) === null) {
            $validator->errors()->add('nik', 'NIK tidak valid.');
        }
    }
}
