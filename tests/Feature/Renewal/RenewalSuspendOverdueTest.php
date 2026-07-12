<?php

namespace Tests\Feature\Renewal;

use App\Models\Sale;
use App\Models\Service;
use App\Notifications\ServiceSuspendedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RenewalSuspendOverdueTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspends_active_overdue_service_with_unpaid_renewal_sale(): void
    {
        Notification::fake();

        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'expired_at' => now()->subDay(),
        ]);
        Sale::factory()->create([
            'service_id' => $service->id,
            'is_renewal' => true,
            'invoiced_at' => now()->subDays(6),
            'expired_at' => null,
        ]);

        Artisan::call('renewal:suspend-overdue');

        $service->refresh();
        $this->assertSame(Service::STATUS_SUSPENDED, $service->status);
        $this->assertNotNull($service->suspended_at);
        Notification::assertSentTo($service->user, ServiceSuspendedNotification::class);
    }

    /**
     * Defensif: Service overdue tanpa Sale renewal sama sekali (mis.
     * renewal:create-invoices gagal/tidak sempat jalan) tetap disuspend —
     * layanan tidak boleh diam-diam terus aktif tanpa tagihan.
     */
    public function test_suspends_overdue_service_even_without_any_renewal_sale(): void
    {
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'expired_at' => now()->subDay(),
        ]);

        Artisan::call('renewal:suspend-overdue');

        $this->assertSame(Service::STATUS_SUSPENDED, $service->fresh()->status);
    }

    public function test_does_not_touch_service_not_yet_expired(): void
    {
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'expired_at' => now()->addDays(2),
        ]);

        Artisan::call('renewal:suspend-overdue');

        $service->refresh();
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
        $this->assertNull($service->suspended_at);
    }

    public function test_does_not_touch_already_suspended_service(): void
    {
        Notification::fake();

        $suspendedAt = now()->subDays(5);
        $service = Service::factory()->create([
            'status' => Service::STATUS_SUSPENDED,
            'expired_at' => now()->subDays(6),
            'suspended_at' => $suspendedAt,
        ]);

        Artisan::call('renewal:suspend-overdue');

        $service->refresh();
        $this->assertSame($suspendedAt->format('Y-m-d H:i:s'), $service->suspended_at->format('Y-m-d H:i:s'));
        Notification::assertNothingSent();
    }

    public function test_does_not_touch_non_active_statuses_with_stale_expiry(): void
    {
        $service = Service::factory()->create([
            'status' => Service::STATUS_CANCELED,
            'expired_at' => now()->subDays(30),
        ]);

        Artisan::call('renewal:suspend-overdue');

        $this->assertSame(Service::STATUS_CANCELED, $service->fresh()->status);
    }
}
