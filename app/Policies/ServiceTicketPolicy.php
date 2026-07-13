<?php

namespace App\Policies;

use App\Models\ServiceTicket;
use App\Models\User;

class ServiceTicketPolicy
{
    // viewAny/view sengaja terbuka untuk technician juga (bukan
    // superadmin-only seperti mayoritas Policy lain) — /tickets adalah
    // resource sendiri (bukan reuse ServicePolicy), jadi tidak perlu method
    // "queue" terpisah seperti Installation/Dismantle untuk melonggarkan
    // akses baca tanpa menyentuh gate modul lain.
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $user->isTechnician();
    }

    public function view(User $user): bool
    {
        return $user->isSuperadmin() || $user->isTechnician();
    }

    // Pembuatan tiket staff-mediated untuk iterasi ini (lihat CLAUDE.md
    // "Ticketing") — customer belum bisa login/API belum ada, jadi
    // superadmin-only, konsisten gate default modul lain.
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

    public function assignTicket(User $user, ServiceTicket $ticket): bool
    {
        return $user->isSuperadmin();
    }

    // Guard "kategori butuh teknisi"/"sudah diklaim" ada di
    // ServiceTicketService (business state), bukan di sini — pola konsisten
    // Policy lain di project ini.
    public function claimTicket(User $user, ServiceTicket $ticket): bool
    {
        return $user->isTechnician();
    }

    // Kondisional kategori: tiket kategori teknis cuma bisa diselesaikan
    // teknisi yang jadi assigned_technician_id (ownership-only, TIDAK ada
    // override superadmin — gap yang sama seperti completeInstallation/
    // completeDismantle, dipertahankan konsisten). Kategori lain
    // diselesaikan langsung superadmin, tanpa penugasan teknisi.
    public function resolveTicket(User $user, ServiceTicket $ticket): bool
    {
        if (in_array($ticket->category, ServiceTicket::CATEGORIES_REQUIRING_TECHNICIAN, true)) {
            return $user->isTechnician() && $ticket->assigned_technician_id === $user->id;
        }

        return $user->isSuperadmin();
    }
}
