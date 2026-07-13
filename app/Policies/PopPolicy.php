<?php

namespace App\Policies;

use App\Models\User;

class PopPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pops.view');
    }

    public function view(User $user): bool
    {
        return $user->can('pops.view');
    }

    public function create(User $user): bool
    {
        return $user->can('pops.create');
    }

    public function update(User $user): bool
    {
        return $user->can('pops.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('pops.delete');
    }
}
