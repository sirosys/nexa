<?php

namespace App\Notifications;

use App\Models\ServiceTicket;
use App\Notifications\Channels\WhatsappChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Dikirim ke teknisi begitu tiket kategori teknis yang sudah in_progress
 * lewat ambang jam tertentu (config('ticket.sla_reminder_hours'), bisa
 * dioverride lewat /settings) tanpa diselesaikan — lihat CLAUDE.md
 * "Ticketing". Dipicu dari ServiceTicketService::sendSlaReminder(), sekali
 * per tiket (kolom service_tickets.sla_reminder_sent_at).
 */
class TicketSlaReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ServiceTicket $ticket,
        private readonly int $hoursOpen,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WhatsappChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Pengingat SLA Tiket',
            'message' => "Tiket {$this->ticket->code} \"{$this->ticket->subject}\" sudah Anda tangani lebih dari {$this->hoursOpen} jam tanpa diselesaikan.",
        ];
    }

    public function toWhatsapp(object $notifiable): string
    {
        $this->ticket->loadMissing('service.user');

        $customer = $this->ticket->service->user;

        return "[NEXA] Pengingat SLA: tiket {$this->ticket->code} \"{$this->ticket->subject}\" milik {$customer?->name} ({$customer?->phone}) sudah Anda tangani lebih dari {$this->hoursOpen} jam tanpa diselesaikan. Mohon segera ditindaklanjuti.";
    }
}
