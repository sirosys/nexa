<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke akun (role apa pun) begitu didaftarkan lewat UserService::create()
 * — lihat CLAUDE.md "User". Tanpa channel mail: walau users.email sekarang
 * field asli (bukan placeholder lagi), scope permintaan ini eksplisit cuma
 * WhatsApp.
 */
class UserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $roleLabel) {}

    public function via(object $notifiable): array
    {
        return ['database', WhatsappChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Akun Terdaftar',
            'message' => "Akun Anda di NEXA telah berhasil didaftarkan sebagai {$this->roleLabel}.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Halo {$notifiable->name}, akun Anda telah berhasil didaftarkan sebagai {$this->roleLabel}.";
    }
}
