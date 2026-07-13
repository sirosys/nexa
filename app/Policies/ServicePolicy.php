<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('services.view');
    }

    public function view(User $user): bool
    {
        return $user->can('services.view');
    }

    public function create(User $user): bool
    {
        return $user->can('services.create');
    }

    public function update(User $user): bool
    {
        return $user->can('services.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('services.delete');
    }

    // Method di bawah ini untuk modul Installation (CLAUDE.md "Installation")
    // — bukan CRUD resource standar, jadi tidak lewat authorizeResource().
    public function viewInstallationQueue(User $user): bool
    {
        return $user->can('installations.view');
    }

    public function assignInstallation(User $user, Service $service): bool
    {
        return $user->can('installations.assign');
    }

    // Guard "sudah diklaim teknisi lain" ada di InstallationService (business
    // state), bukan di sini — Policy tetap murni cek permission, pola
    // konsisten Policy lain di project ini. isTechnician() tetap wajib di
    // samping permission — klaim adalah aksi fieldwork, superadmin sengaja
    // TIDAK ikut kebagian walau mereka punya seluruh permission (beda dari
    // completeInstallation di bawah yang justru sengaja dibuka lewat
    // installations.complete-any) — superadmin mendelegasikan lewat assign,
    // bukan mengerjakan sendiri.
    public function claimInstallation(User $user, Service $service): bool
    {
        return $user->isTechnician() && $user->can('installations.claim');
    }

    // Ownership (installations.complete) ATAU override (installations.complete-any,
    // dipegang superadmin) — override menutup gap "job stuck permanen" kalau
    // teknisi yang di-assign resign/tidak aktif (CLAUDE.md "Installation").
    public function completeInstallation(User $user, Service $service): bool
    {
        if ($user->can('installations.complete-any')) {
            return true;
        }

        return $user->can('installations.complete') && $service->activation?->installer_id === $user->id;
    }

    // Method di bawah ini untuk modul Dismantle (CLAUDE.md "Dismantle") —
    // mirror 4 method Installation di atas, plus queueDismantle untuk
    // trigger manual staff (Installation tidak butuh ini karena jalur
    // masuk antreannya tunggal, dari webhook Billing).
    public function viewDismantleQueue(User $user): bool
    {
        return $user->can('dismantles.view');
    }

    public function queueDismantle(User $user, Service $service): bool
    {
        return $user->can('dismantles.queue');
    }

    public function assignDismantle(User $user, Service $service): bool
    {
        return $user->can('dismantles.assign');
    }

    // isTechnician() tetap wajib di samping permission, alasan sama seperti
    // claimInstallation di atas.
    public function claimDismantle(User $user, Service $service): bool
    {
        return $user->isTechnician() && $user->can('dismantles.claim');
    }

    // Ownership ATAU override — pola sama persis completeInstallation di atas.
    public function completeDismantle(User $user, Service $service): bool
    {
        if ($user->can('dismantles.complete-any')) {
            return true;
        }

        return $user->can('dismantles.complete') && $service->dismantle?->technician_id === $user->id;
    }
}
