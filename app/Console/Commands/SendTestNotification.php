<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\TestNotification;
use App\Services\NotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notify:test {--user= : ID user tujuan} {--phone= : Nomor telepon user tujuan (format 62xxxx)} {--message=Ini adalah notifikasi uji coba dari NEXA. : Isi pesan}')]
#[Description('Kirim notifikasi contoh (in-app, WhatsApp, email) ke satu user untuk verifikasi manual end-to-end tanpa event bisnis.')]
class SendTestNotification extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notifications): int
    {
        $user = $this->option('user')
            ? User::find($this->option('user'))
            : User::where('phone', $this->option('phone'))->first();

        if (! $user) {
            $this->error('User tidak ditemukan. Gunakan --user=ID atau --phone=62xxxx.');

            return self::FAILURE;
        }

        $notifications->send($user, new TestNotification((string) $this->option('message')));

        $this->info("Notifikasi dikirim ke user #{$user->id} ({$user->phone}). Cek tabel notifications, log WhatsApp (storage/logs/laravel.log), dan log mail. Kalau salah satu channel gagal (mis. mail bounce), lihat storage/logs/laravel.log untuk warning-nya — channel lain tetap tidak terpengaruh.");

        return self::SUCCESS;
    }
}
