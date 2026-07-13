<?php

namespace App\Policies;

use App\Models\User;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vendors.view');
    }

    public function view(User $user): bool
    {
        return $user->can('vendors.view');
    }

    public function create(User $user): bool
    {
        return $user->can('vendors.create');
    }

    public function update(User $user): bool
    {
        return $user->can('vendors.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('vendors.delete');
    }
}
