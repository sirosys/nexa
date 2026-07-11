<?php

namespace App\Notifications;

use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic notification used only to verify the Notification module's
 * plumbing (database + WhatsApp + mail channel) end-to-end via the
 * `notify:test` artisan command. Not wired to any real business event.
 */
class TestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $message) {}

    public function via(object $notifiable): array
    {
        return ['database', WhatsappChannel::class, 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Notifikasi Uji Coba',
            'message' => $this->message,
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] {$this->message}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Notifikasi Uji Coba NEXA')
            ->line($this->message);
    }
}
