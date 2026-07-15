<?php

namespace App\Policies;

use App\Models\User;

class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('plans.view');
    }

    public function view(User $user): bool
    {
        return $user->can('plans.view');
    }

    public function create(User $user): bool
    {
        return $user->can('plans.create');
    }

    public function update(User $user): bool
    {
        return $user->can('plans.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('plans.delete');
    }
}
