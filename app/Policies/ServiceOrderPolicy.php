<?php

namespace App\Policies;

use App\Models\User;

class ServiceOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('service_orders.view');
    }

    public function view(User $user): bool
    {
        return $user->can('service_orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('service_orders.create');
    }

    public function update(User $user): bool
    {
        return $user->can('service_orders.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('service_orders.delete');
    }

    // Terpisah dari update() penuh — supaya finance bisa retry link
    // pembayaran tanpa diberi akses ubah line item/harga Order Layanan.
    public function retryReceipt(User $user): bool
    {
        return $user->can('service_orders.retry-receipt');
    }
}
