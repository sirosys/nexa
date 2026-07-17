<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\ServiceTicket;
use App\Models\User;

// `$user->can('tickets.*')` di seluruh method di bawah ini resolve lewat
// guard Spatie `guard_name='web'` (config('auth.defaults.guard') = 'web',
// User tidak override guard_name) — tetap berlaku walau request datang dari
// auth:sanctum (API customer-facing), karena Spatie tidak terikat ke guard
// autentikasi request, cuma ke guard_name row Role/Permission. Kalau
// AUTH_GUARD berubah nanti, verifikasi ulang asumsi ini.
class ServiceTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view');
    }

    public function view(User $user): bool
    {
        return $user->can('tickets.view');
    }

    /**
     * `$service` opsional dipakai API customer-facing (lihat CLAUDE.md "API
     * Customer-Facing") — pelanggan boleh buat tiket untuk Service miliknya
     * sendiri tanpa permission `tickets.create` (yang cuma dipegang staff).
     * Call site admin (`authorizeResource()` di ServiceTicketController)
     * tetap memanggil `create` cuma dengan `User`, jadi `$service` selalu
     * null di situ — tidak ada perubahan perilaku untuk staff.
     */
    public function create(User $user, ?Service $service = null): bool
    {
        if ($user->can('tickets.create')) {
            return true;
        }

        return $service !== null && $service->user_id === $user->id;
    }

    public function update(User $user): bool
    {
        return $user->can('tickets.update');
    }

    public function delete(User $user): bool
    {
        return $user->can('tickets.delete');
    }

    public function assignTicket(User $user, ServiceTicket $ticket): bool
    {
        return $user->can('tickets.assign');
    }

    // Guard "kategori butuh teknisi"/"sudah diklaim" ada di
    // ServiceTicketService (business state), bukan di sini — pola konsisten
    // Policy lain di project ini. isTechnician() tetap wajib di samping
    // permission, alasan sama seperti claimInstallation di ServicePolicy —
    // klaim adalah aksi fieldwork, superadmin sengaja tidak ikut kebagian
    // walau punya seluruh permission.
    public function claimTicket(User $user, ServiceTicket $ticket): bool
    {
        return $user->isTechnician() && $user->can('tickets.claim');
    }

    // tickets.resolve-any (dipegang superadmin) mencakup DUA hal sekaligus:
    // (1) override ownership utk tiket kategori teknis (menutup gap "job
    // stuck" kalau teknisi yang di-assign resign), DAN (2) resolusi tiket
    // kategori non-teknis (yang memang selalu superadmin-only, tidak lewat
    // penugasan teknisi) — tidak perlu permission ketiga karena keduanya
    // sama-sama "boleh selesaikan tiket apa pun".
    public function resolveTicket(User $user, ServiceTicket $ticket): bool
    {
        if ($user->can('tickets.resolve-any')) {
            return true;
        }

        return in_array($ticket->category, ServiceTicket::CATEGORIES_REQUIRING_TECHNICIAN, true)
            && $user->can('tickets.resolve')
            && $ticket->assigned_technician_id === $user->id;
    }
}
