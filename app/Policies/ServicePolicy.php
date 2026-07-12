<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function view(User $user): bool
    {
        return $user->isSuperadmin();
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

    // Method di bawah ini untuk modul Installation (CLAUDE.md "Installation")
    // — bukan CRUD resource standar, jadi tidak lewat authorizeResource().
    // Ini juga policy pertama di NEXA yang memberi akses nyata ke role
    // selain superadmin.
    public function viewInstallationQueue(User $user): bool
    {
        return $user->isSuperadmin() || $user->isTechnician();
    }

    public function assignInstallation(User $user, Service $service): bool
    {
        return $user->isSuperadmin();
    }

    // Guard "sudah diklaim teknisi lain" ada di InstallationService (business
    // state), bukan di sini — Policy tetap murni cek role, pola konsisten
    // Policy lain di project ini.
    public function claimInstallation(User $user, Service $service): bool
    {
        return $user->isTechnician();
    }

    // Ownership-only, sengaja TIDAK ada override superadmin (gap
    // terdokumentasi di CLAUDE.md "Installation").
    public function completeInstallation(User $user, Service $service): bool
    {
        return $user->isTechnician() && $service->activation?->installer_id === $user->id;
    }

    // Method di bawah ini untuk modul Dismantle (CLAUDE.md "Dismantle") —
    // mirror 4 method Installation di atas, plus queueDismantle untuk
    // trigger manual staff (Installation tidak butuh ini karena jalur
    // masuk antreannya tunggal, dari webhook Billing).
    public function viewDismantleQueue(User $user): bool
    {
        return $user->isSuperadmin() || $user->isTechnician();
    }

    public function queueDismantle(User $user, Service $service): bool
    {
        return $user->isSuperadmin();
    }

    public function assignDismantle(User $user, Service $service): bool
    {
        return $user->isSuperadmin();
    }

    public function claimDismantle(User $user, Service $service): bool
    {
        return $user->isTechnician();
    }

    // Ownership-only, TIDAK ada override superadmin — konsisten
    // completeInstallation (gap yang sama, lihat CLAUDE.md "Dismantle").
    public function completeDismantle(User $user, Service $service): bool
    {
        return $user->isTechnician() && $service->dismantle?->technician_id === $user->id;
    }
}
