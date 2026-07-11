<?php

namespace App\Services;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotificationService
{
    /**
     * Send a notification, isolating failures per-notifiable so that a
     * broken channel (e.g. mail bouncing because users.email is still an
     * inert placeholder) never crashes the caller. Channels that already
     * succeeded before the failing one (e.g. database/WhatsApp ahead of
     * mail in via()) keep their effect regardless — this only prevents
     * the failure from propagating up into business-flow code.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (method_exists($notification, 'onConnection')) {
            $notification->onConnection(config('notification.queue_connection'));
        }

        try {
            $notifiable->notify($notification);
        } catch (Throwable $exception) {
            Log::warning('Notifikasi gagal terkirim di salah satu channel', [
                'notifiable_type' => $notifiable::class,
                'notifiable_id' => $notifiable->id ?? null,
                'notification' => $notification::class,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
