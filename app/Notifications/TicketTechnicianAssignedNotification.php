<?php

namespace App\Notifications;

use App\Models\ServiceTicket;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke teknisi begitu sebuah tiket kategori teknis
 * ditugaskan/diklaim untuknya — lihat CLAUDE.md "Ticketing". Dipicu dari
 * ServiceTicketService::assign() saja (bukan claim() — pola sama
 * TechnicianAssignedNotification milik Installation, teknisi yang klaim
 * sendiri sudah tahu tanpa perlu diberi tahu).
 */
class TicketTechnicianAssignedNotification extends Notification implements ShouldQueue
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
            'title' => 'Tiket Ditugaskan',
            'message' => "Anda ditugaskan menangani tiket {$this->ticket->code} \"{$this->ticket->subject}\".",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $this->ticket->loadMissing('service.user');

        $customer = $this->ticket->service->user;

        return "[NEXA] Anda ditugaskan menangani tiket {$this->ticket->code} \"{$this->ticket->subject}\" milik {$customer?->name} ({$customer?->phone}).";
    }
}
