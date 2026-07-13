<?php

namespace App\Policies;

use App\Models\User;

class PackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('packages.view');
    }

    public function view(User $user): bool
    {
        return $user->can('packages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('packages.create');
    }

    public function update(User $user): bool
    {
        return $user->can('packages.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('packages.delete');
    }
}
