<?php

namespace App\Console\Commands;

use App\Models\ServiceTicket;
use App\Models\Setting;
use App\Services\ServiceTicketService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('tickets:send-sla-reminders')]
#[Description('Kirim pengingat SLA untuk tiket kategori teknis yang sudah in_progress terlalu lama tanpa diselesaikan')]
class TicketsSendSlaReminders extends Command
{
    public function handle(ServiceTicketService $ticketService): int
    {
        $overdue = $this->overdue();

        foreach ($overdue as $ticket) {
            $ticketService->sendSlaReminder($ticket);
        }

        $this->info("Pengingat SLA terkirim: {$overdue->count()} tiket.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, ServiceTicket>
     */
    private function overdue(): Collection
    {
        $hours = (int) Setting::get('ticket.sla_reminder_hours', config('ticket.sla_reminder_hours'));

        return ServiceTicket::query()
            ->where('status', ServiceTicket::STATUS_IN_PROGRESS)
            ->whereNotNull('assigned_technician_id')
            ->whereNull('sla_reminder_sent_at')
            ->whereNotNull('claimed_at')
            ->where('claimed_at', '<=', now()->subHours($hours))
            ->with('service.user', 'assignedTechnician')
            ->get();
    }
}
