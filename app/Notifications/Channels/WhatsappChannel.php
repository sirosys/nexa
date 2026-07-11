<?php

namespace App\Notifications\Channels;

use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Notifications\Notification;

/**
 * Not registered via a service provider — referenced directly as a
 * class-string in a notification's via() (e.g. WhatsappChannel::class).
 * Laravel's container resolves this class (and its WhatsappGateway
 * dependency, already bound in WhatsappServiceProvider) automatically.
 */
class WhatsappChannel
{
    public function __construct(private readonly WhatsappGateway $gateway) {}

    public function send(object $notifiable, Notification $notification): bool
    {
        if (! method_exists($notification, 'toWhatsapp')) {
            return false;
        }

        $phone = method_exists($notifiable, 'routeNotificationForWhatsapp')
            ? $notifiable->routeNotificationForWhatsapp($notification)
            : null;

        $phone ??= $notifiable->phone ?? null;

        if (! $phone) {
            return false;
        }

        return $this->gateway->sendMessage((string) $phone, (string) $notification->toWhatsapp($notifiable));
    }
}
