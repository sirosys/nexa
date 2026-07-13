<?php

namespace App\Notifications;

use App\Models\ServiceTicket;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke customer begitu tiket keluhan/permintaannya tercatat — lihat
 * CLAUDE.md "Ticketing". Dipicu dari ServiceTicketService::create().
 */
class TicketCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ServiceTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['database', WhatsappChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tiket Dibuat',
            'message' => "Tiket {$this->ticket->code} \"{$this->ticket->subject}\" sudah tercatat dan akan segera ditindaklanjuti.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return "[NEXA] Halo {$notifiable->name}, tiket Anda ({$this->ticket->code}) \"{$this->ticket->subject}\" sudah tercatat dan akan segera ditindaklanjuti.";
    }
}
