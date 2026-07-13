<?php

namespace App\Policies;

use App\Models\User;

class InventoryItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.create');
    }

    public function delete(User $user): bool
    {
        return $user->can('inventory.delete');
    }

    // Terpisah dari create/delete — InventoryItemRequest tidak punya route
    // edit/update sama sekali (product_id/is_serialized immutable), jadi
    // "update" generik tidak relevan di sini, cuma dua aksi mutasi stok ini.
    public function stockIn(User $user): bool
    {
        return $user->can('inventory.stock-in');
    }

    public function adjust(User $user): bool
    {
        return $user->can('inventory.adjust');
    }
}
