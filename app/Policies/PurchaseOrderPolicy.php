<?php

namespace App\Policies;

use App\Models\User;

class PurchaseOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchase_orders.view');
    }

    public function view(User $user): bool
    {
        return $user->can('purchase_orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchase_orders.create');
    }

    public function update(User $user): bool
    {
        return $user->can('purchase_orders.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('purchase_orders.delete');
    }

    public function order(User $user): bool
    {
        return $user->can('purchase_orders.order');
    }

    public function receive(User $user): bool
    {
        return $user->can('purchase_orders.receive');
    }

    public function cancel(User $user): bool
    {
        return $user->can('purchase_orders.cancel');
    }
}
