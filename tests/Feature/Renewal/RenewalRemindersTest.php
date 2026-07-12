<?php

namespace Tests\Feature\Renewal;

use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Service;
use App\Notifications\RenewalReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RenewalRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function unpaidRenewalSale(int $expiresInDays): Sale
    {
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'expired_at' => now()->addDays($expiresInDays),
        ]);

        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'is_renewal' => true,
            'grandtotal' => 200000,
            'invoiced_at' => now()->subDays(2),
            'expired_at' => null,
        ]);

        Receipt::factory()->create([
            'sale_id' => $sale->id,
            'status' => Receipt::STATUS_AWAITING_CHANNEL_SELECTION,
            'xendit_payment_request_id' => null,
        ]);

        return $sale;
    }

    public function test_sends_h3_reminder_once_and_stamps_column(): void
    {
        Notification::fake();

        $sale = $this->unpaidRenewalSale(3);

        Artisan::call('renewal:send-reminders');

        $sale->refresh();
        $this->assertNotNull($sale->renewal_reminder_h3_sent_at);
        Notification::assertSentToTimes($sale->service->user, RenewalReminderNotification::class, 1);
    }

    public function test_does_not_resend_h3_reminder_on_second_run(): void
    {
        Notification::fake();

        $sale = $this->unpaidRenewalSale(3);

        Artisan::call('renewal:send-reminders');
        Artisan::call('renewal:send-reminders');

        Notification::assertSentToTimes($sale->service->user, RenewalReminderNotification::class, 1);
    }

    /**
     * H-3 sudah terkirim di siklus sebelumnya (kolomnya sudah terisi) —
     * memastikan pass H-1 tetap jalan independen dari status H-3, dan tidak
     * mengirim ulang H-3. Sengaja tidak menguji H-1 dari kondisi kosong
     * (expired_at 1 hari lagi otomatis juga masuk window H-3 yang lebih
     * lebar, <=3 hari — dua pass akan sama-sama terkirim di run yang sama,
     * ini adalah perilaku self-healing yang disengaja, bukan bug).
     */
    public function test_sends_h1_reminder_independently_of_h3(): void
    {
        Notification::fake();

        $sale = $this->unpaidRenewalSale(1);
        $sale->update(['renewal_reminder_h3_sent_at' => now()->subDays(2)]);

        Artisan::call('renewal:send-reminders');

        $sale->refresh();
        $this->assertNotNull($sale->renewal_reminder_h1_sent_at);
        Notification::assertSentToTimes($sale->service->user, RenewalReminderNotification::class, 1);
    }

    public function test_does_not_remind_settled_sale(): void
    {
        Notification::fake();

        $sale = $this->unpaidRenewalSale(2);
        $sale->update(['settled_at' => now()]);

        Artisan::call('renewal:send-reminders');

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_canceled_sale(): void
    {
        Notification::fake();

        $sale = $this->unpaidRenewalSale(2);
        $sale->update(['canceled_at' => now()]);

        Artisan::call('renewal:send-reminders');

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_registration_sale(): void
    {
        Notification::fake();

        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'expired_at' => now()->addDays(3),
        ]);

        Sale::factory()->create([
            'service_id' => $service->id,
            'is_renewal' => false,
            'invoiced_at' => now()->subDays(2),
            'expired_at' => now()->subDay(),
        ]);

        Artisan::call('renewal:send-reminders');

        Notification::assertNothingSent();
    }
}
