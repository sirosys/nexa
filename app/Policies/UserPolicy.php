<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user): bool
    {
        return $user->can('users.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('users.delete');
    }

    // Aksi terpisah dari update() penuh — dipakai gate endpoint
    // POST /users/{user}/complete-kyc (dipicu dari modal "Lengkapi NIK &
    // Foto KTP" di form Service), supaya role staff non-superadmin
    // (technician/finance) bisa menyelesaikan KYC pelanggan yang baru
    // dibuat tanpa diberi akses users.update penuh.
    public function completeKyc(User $user): bool
    {
        return $user->can('users.complete-kyc');
    }
}
