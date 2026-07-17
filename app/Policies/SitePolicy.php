<?php

namespace App\Policies;

use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sites.view');
    }

    public function view(User $user): bool
    {
        return $user->can('sites.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sites.create');
    }

    public function update(User $user): bool
    {
        return $user->can('sites.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('sites.delete');
    }
}
