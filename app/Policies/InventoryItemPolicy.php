<?php

namespace App\Policies;

use App\Models\User;

class InventoryItemPolicy
{
    // viewAny/view terbuka untuk technician juga — teknisi perlu melihat
    // stok tersedia saat memilih equipment di form penyelesaian instalasi
    // (lihat CLAUDE.md "Inventaris"), pola sama ServiceTicketPolicy.
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $user->isTechnician();
    }

    public function view(User $user): bool
    {
        return $user->isSuperadmin() || $user->isTechnician();
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function delete(User $user): bool
    {
        return $user->isSuperadmin();
    }
}
