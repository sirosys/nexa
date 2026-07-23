<?php

namespace App\Services;

use App\Models\ServiceTicket;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Notifications\TicketSlaReminderNotification;
use App\Notifications\TicketTechnicianAssignedNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ServiceTicketService
{
    public function __construct(private readonly NotificationService $notificationService) {}

    /**
     * @param  array{service_id: int, category: string, subject: string, description: string}  $data
     */
    public function create(array $data): ServiceTicket
    {
        $ticket = DB::transaction(function () use ($data) {
            $ticket = ServiceTicket::create([
                'service_id' => $data['service_id'],
                'category' => $data['category'],
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => ServiceTicket::STATUS_OPEN,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $ticket->update(['code' => 'TIK'.str_pad((string) $ticket->id, 6, '0', STR_PAD_LEFT)]);

            return $ticket;
        });

        $ticket->loadMissing('service.user');
        $this->notificationService->send($ticket->service->user, new TicketCreatedNotification($ticket));

        return $ticket;
    }

    /**
     * @param  array{service_id: int, category: string, subject: string, description: string}  $data
     */
    public function update(ServiceTicket $ticket, array $data): ServiceTicket
    {
        $ticket->update([
            'service_id' => $data['service_id'],
            'category' => $data['category'],
            'subject' => $data['subject'],
            'description' => $data['description'],
            'updated_by' => Auth::id(),
        ]);

        return $ticket;
    }

    public function delete(ServiceTicket $ticket): void
    {
        $ticket->delete();
    }

    public function assign(ServiceTicket $ticket, User $technician, User $assignedBy): ServiceTicket
    {
        $this->guardOpenForTechnicianAssignment($ticket);

        $ticket->update([
            'assigned_technician_id' => $technician->id,
            'assigned_by' => $assignedBy->id,
            'claimed_at' => now(),
            'status' => ServiceTicket::STATUS_IN_PROGRESS,
            'updated_by' => Auth::id(),
        ]);

        $this->notificationService->send($technician, new TicketTechnicianAssignedNotification($ticket));

        return $ticket;
    }

    public function claim(ServiceTicket $ticket, User $technician): ServiceTicket
    {
        $this->guardOpenForTechnicianAssignment($ticket);

        $ticket->update([
            'assigned_technician_id' => $technician->id,
            'assigned_by' => null,
            'claimed_at' => now(),
            'status' => ServiceTicket::STATUS_IN_PROGRESS,
            'updated_by' => Auth::id(),
        ]);

        return $ticket;
    }

    public function resolve(ServiceTicket $ticket, User $resolver, ?string $notes): ServiceTicket
    {
        $requiresTechnician = in_array($ticket->category, ServiceTicket::CATEGORIES_REQUIRING_TECHNICIAN, true);

        if ($requiresTechnician && $ticket->status !== ServiceTicket::STATUS_IN_PROGRESS) {
            throw new RuntimeException('Tiket ini belum ditugaskan/diklaim teknisi.');
        }

        if (! $requiresTechnician && $ticket->status !== ServiceTicket::STATUS_OPEN) {
            throw new RuntimeException('Tiket ini sudah tidak berstatus terbuka.');
        }

        $ticket->update([
            'status' => ServiceTicket::STATUS_RESOLVED,
            'resolution_notes' => $notes,
            'solved_at' => now(),
            'solved_by' => $resolver->id,
            'updated_by' => Auth::id(),
        ]);

        $ticket->loadMissing('service.user');
        $this->notificationService->send($ticket->service->user, new TicketResolvedNotification($ticket));

        return $ticket;
    }

    public function sendSlaReminder(ServiceTicket $ticket): void
    {
        $hoursOpen = (int) $ticket->claimed_at->diffInHours(now());

        $ticket->update(['sla_reminder_sent_at' => now()]);

        $this->notificationService->send($ticket->assignedTechnician, new TicketSlaReminderNotification($ticket, $hoursOpen));
    }

    private function guardOpenForTechnicianAssignment(ServiceTicket $ticket): void
    {
        if (! in_array($ticket->category, ServiceTicket::CATEGORIES_REQUIRING_TECHNICIAN, true)) {
            throw new RuntimeException('Kategori tiket ini tidak memerlukan penugasan teknisi.');
        }

        if ($ticket->status !== ServiceTicket::STATUS_OPEN) {
            throw new RuntimeException('Tiket ini tidak dalam status terbuka.');
        }

        if ($ticket->assigned_technician_id !== null) {
            throw new RuntimeException('Tiket ini sudah ditugaskan/diklaim teknisi lain.');
        }
    }
}
