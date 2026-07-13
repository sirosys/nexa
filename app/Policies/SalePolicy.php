<?php

namespace App\Policies;

use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    public function view(User $user): bool
    {
        return $user->can('sales.view');
    }

    public function create(User $user): bool
    {
        return $user->can('sales.create');
    }

    public function update(User $user): bool
    {
        return $user->can('sales.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('sales.delete');
    }

    // Terpisah dari update() penuh — supaya finance bisa retry link
    // pembayaran tanpa diberi akses ubah line item/harga Sale.
    public function retryReceipt(User $user): bool
    {
        return $user->can('sales.retry-receipt');
    }
}
