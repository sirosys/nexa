<?php

namespace App\Http\Requests;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $role = $this->route('role');
        $roleId = $role?->id;

        // Role bawaan (superadmin/technician/finance/customer) selalu
        // dirender dengan field nama disabled (lihat roles/edit.blade.php),
        // jadi tidak pernah ikut ter-submit — 'name' harus nullable untuk
        // route itu supaya tidak gagal validasi cuma karena tidak ada di
        // payload. RoleService::updatePermissions() tetap mengabaikan 'name'
        // untuk role bawaan sebagai lapisan kedua kalau ada yang tamper
        // request langsung.
        $isBuiltInRole = $role && in_array($role->name, RoleSeeder::ROLES, true);

        return [
            'name' => [
                $isBuiltInRole ? 'nullable' : 'required',
                'string',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where(fn ($query) => $query->where('guard_name', 'web'))->ignore($roleId),
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama role wajib diisi.',
            'name.max' => 'Nama role maksimal 50 karakter.',
            'name.regex' => 'Nama role hanya boleh huruf kecil, angka, dan underscore, diawali huruf (mis. "warehouse_staff").',
            'name.unique' => 'Nama role ini sudah dipakai.',

            'permissions.array' => 'Format permission tidak valid.',
            'permissions.*.exists' => 'Salah satu permission yang dipilih tidak valid.',
        ];
    }
}
