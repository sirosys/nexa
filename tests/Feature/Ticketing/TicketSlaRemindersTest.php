<?php

namespace Tests\Feature\Ticketing;

use App\Models\Service;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Notifications\TicketSlaReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketSlaRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function technician(): User
    {
        $user = User::factory()->create();
        $user->assignRole('technician');

        return $user;
    }

    private function ticket(array $overrides = []): ServiceTicket
    {
        if (! isset($overrides['service_id'])) {
            $overrides['service_id'] = Service::factory()->create()->id;
        }

        return ServiceTicket::create(array_merge([
            'code' => 'TIK'.random_int(100000, 999999),
            'category' => ServiceTicket::CATEGORY_TEKNIS,
            'subject' => 'Internet lambat',
            'description' => 'Kecepatan turun drastis sejak kemarin.',
            'status' => ServiceTicket::STATUS_OPEN,
        ], $overrides));
    }

    private function overdueTicket(int $claimedHoursAgo = 30): ServiceTicket
    {
        $technician = $this->technician();

        return $this->ticket([
            'category' => ServiceTicket::CATEGORY_TEKNIS,
            'status' => ServiceTicket::STATUS_IN_PROGRESS,
            'assigned_technician_id' => $technician->id,
            'claimed_at' => now()->subHours($claimedHoursAgo),
        ]);
    }

    public function test_sends_sla_reminder_once_and_stamps_column(): void
    {
        Notification::fake();

        $ticket = $this->overdueTicket();

        Artisan::call('tickets:send-sla-reminders');

        $ticket->refresh();
        $this->assertNotNull($ticket->sla_reminder_sent_at);
        Notification::assertSentToTimes($ticket->assignedTechnician, TicketSlaReminderNotification::class, 1);
    }

    public function test_does_not_resend_sla_reminder_on_second_run(): void
    {
        Notification::fake();

        $ticket = $this->overdueTicket();

        Artisan::call('tickets:send-sla-reminders');
        Artisan::call('tickets:send-sla-reminders');

        Notification::assertSentToTimes($ticket->assignedTechnician, TicketSlaReminderNotification::class, 1);
    }

    public function test_does_not_remind_ticket_claimed_within_threshold(): void
    {
        Notification::fake();

        $this->overdueTicket(claimedHoursAgo: 5);

        Artisan::call('tickets:send-sla-reminders');

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_open_unassigned_ticket(): void
    {
        Notification::fake();

        $this->ticket([
            'category' => ServiceTicket::CATEGORY_TEKNIS,
            'status' => ServiceTicket::STATUS_OPEN,
        ]);

        Artisan::call('tickets:send-sla-reminders');

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_non_technical_category_ticket(): void
    {
        Notification::fake();

        $technician = $this->technician();
        $this->ticket([
            'category' => ServiceTicket::CATEGORY_BILLING,
            'status' => ServiceTicket::STATUS_OPEN,
            'assigned_technician_id' => $technician->id,
            'claimed_at' => now()->subHours(48),
        ]);

        Artisan::call('tickets:send-sla-reminders');

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_resolved_ticket(): void
    {
        Notification::fake();

        $technician = $this->technician();
        $this->ticket([
            'category' => ServiceTicket::CATEGORY_TEKNIS,
            'status' => ServiceTicket::STATUS_RESOLVED,
            'assigned_technician_id' => $technician->id,
            'claimed_at' => now()->subHours(48),
            'solved_at' => now()->subHour(),
            'solved_by' => $technician->id,
        ]);

        Artisan::call('tickets:send-sla-reminders');

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_soft_deleted_ticket(): void
    {
        Notification::fake();

        $ticket = $this->overdueTicket();
        $ticket->delete();

        Artisan::call('tickets:send-sla-reminders');

        Notification::assertNothingSent();
    }

    public function test_command_output_reports_count_sent(): void
    {
        Notification::fake();

        $this->overdueTicket();
        $this->overdueTicket();

        Artisan::call('tickets:send-sla-reminders');

        $this->assertStringContainsString('Pengingat SLA terkirim: 2 tiket.', Artisan::output());
    }
}
