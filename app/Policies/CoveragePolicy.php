<?php

namespace App\Policies;

use App\Models\User;

class CoveragePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('coverages.view');
    }

    public function view(User $user): bool
    {
        return $user->can('coverages.view');
    }

    public function create(User $user): bool
    {
        return $user->can('coverages.create');
    }

    public function update(User $user): bool
    {
        return $user->can('coverages.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('coverages.delete');
    }
}
