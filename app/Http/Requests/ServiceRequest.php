<?php

namespace App\Http\Requests;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'address' => ['required', 'string'],
            'residential_name' => ['nullable', 'string', 'max:255'],
            'subdistrict_id' => ['required', 'integer', 'exists:subdistricts,id'],
            'rw' => ['nullable', 'string', 'max:10'],
            'rt' => ['nullable', 'string', 'max:10'],
            'coverage_id' => ['required', 'integer', Rule::exists('coverages', 'id')],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')],
            'pin' => [$this->isMethod('put') || $this->isMethod('patch') ? 'required' : 'nullable', 'digits:6'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $userId = $this->input('user_id');

            if (! $userId) {
                return;
            }

            $user = User::find($userId);

            if ($user && ! $user->hasRole('customer')) {
                $validator->errors()->add('user_id', 'User yang dipilih harus berrole customer.');
            }

            $packageId = $this->input('package_id');

            if ($packageId) {
                $package = Package::find($packageId);

                if ($package && ! $package->is_starter) {
                    $validator->errors()->add('package_id', 'Paket yang dipilih tidak tersedia untuk pendaftaran baru.');
                }
            }
        });
    }
}
