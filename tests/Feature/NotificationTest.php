<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TestNotification;
use App\Services\NotificationService;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification as NotificationBase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    public function test_notify_test_command_stores_database_notification(): void
    {
        $user = User::factory()->create(['phone' => '6281111111111']);

        $exitCode = Artisan::call('notify:test', ['--user' => $user->id, '--message' => 'Halo dari test']);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
        $this->assertSame('Halo dari test', $user->fresh()->notifications()->first()->data['message']);
    }

    public function test_notify_test_command_sends_via_whatsapp_gateway(): void
    {
        $gateway = $this->fakeGateway();
        $user = User::factory()->create(['phone' => '6282222222222']);

        Artisan::call('notify:test', ['--user' => $user->id, '--message' => 'Pesan WhatsApp']);

        $this->assertSame('6282222222222', $gateway->phone);
        $this->assertSame('[NEXA] Pesan WhatsApp', $gateway->message);
    }

    public function test_notify_test_command_sends_via_mail_channel(): void
    {
        Notification::fake();
        $user = User::factory()->create(['phone' => '6283333333333']);

        Artisan::call('notify:test', ['--user' => $user->id]);

        Notification::assertSentTo($user, TestNotification::class);
    }

    public function test_notify_test_command_rejects_unknown_user(): void
    {
        $exitCode = Artisan::call('notify:test', ['--phone' => '6289999999999']);

        $this->assertSame(1, $exitCode);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_mark_as_read_marks_own_notification(): void
    {
        $user = User::factory()->create();
        $user->notify(new TestNotification('Punya sendiri'));
        $notification = $user->notifications()->first();

        $response = $this->actingAs($user)->post(route('notifications.read', $notification->id));

        $response->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_rejects_other_users_notification(): void
    {
        $owner = User::factory()->create();
        $owner->notify(new TestNotification('Punya orang lain'));
        $notification = $owner->notifications()->first();

        $intruder = User::factory()->create();

        $response = $this->actingAs($intruder)->post(route('notifications.read', $notification->id));

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_marks_all_unread_for_current_user_only(): void
    {
        $userA = User::factory()->create();
        $userA->notify(new TestNotification('A1'));
        $userA->notify(new TestNotification('A2'));

        $userB = User::factory()->create();
        $userB->notify(new TestNotification('B1'));

        $this->actingAs($userA)->post(route('notifications.read-all'));

        $this->assertSame(0, $userA->fresh()->unreadNotifications()->count());
        $this->assertSame(1, $userB->fresh()->unreadNotifications()->count());
    }

    public function test_header_bell_shows_unread_count(): void
    {
        $user = User::factory()->create();
        $user->notify(new TestNotification('Notifikasi Bell'));

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Notifikasi Bell');
    }

    public function test_notification_service_isolates_channel_failures(): void
    {
        $user = User::factory()->create();

        // 'database' runs before the intentionally-throwing channel in via() —
        // its effect must survive even though the later channel fails and
        // NotificationService::send() must not let that exception escape.
        app(NotificationService::class)->send($user, new MixedChannelTestNotification);

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }
}

class MixedChannelTestNotification extends NotificationBase
{
    public function via(object $notifiable): array
    {
        return ['database', ThrowingNotificationChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Mixed', 'message' => 'Uji ketahanan channel'];
    }
}

class ThrowingNotificationChannel
{
    public function send(object $notifiable, NotificationBase $notification): void
    {
        throw new \RuntimeException('Simulated channel failure');
    }
}
