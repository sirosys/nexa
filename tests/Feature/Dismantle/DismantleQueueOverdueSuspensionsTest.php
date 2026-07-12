<?php

namespace Tests\Feature\Dismantle;

use App\Models\Sale;
use App\Models\Service;
use App\Models\ServiceActivation;
use App\Models\ServiceDismantle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class DismantleQueueOverdueSuspensionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Service dengan status+suspended_at tertentu, plus ServiceActivation
     * settled — prasyarat DismantleService::queue() (beda dari
     * RenewalSuspendOverdueTest yang tidak butuh activation sama sekali,
     * karena RenewalService::suspend() tidak menyentuh activation_id).
     */
    private function serviceWithActivation(array $overrides = []): Service
    {
        $service = Service::factory()->create($overrides);

        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'package_id' => $service->package_id,
            'settled_at' => now(),
        ]);

        ServiceActivation::create([
            'service_id' => $service->id,
            'sale_id' => $sale->id,
        ]);

        return $service;
    }

    public function test_queues_suspended_service_past_threshold(): void
    {
        $service = $this->serviceWithActivation([
            'status' => Service::STATUS_SUSPENDED,
            'suspended_at' => now()->subMonths(3),
        ]);

        Artisan::call('dismantle:queue-overdue-suspensions');

        $service->refresh();
        $this->assertSame(Service::STATUS_PENDING_DISMANTLE, $service->status);

        $dismantle = ServiceDismantle::where('service_id', $service->id)->firstOrFail();
        $this->assertNull($dismantle->queued_by);
        $this->assertSame($service->activation->id, $dismantle->activation_id);
    }

    public function test_does_not_touch_suspended_service_under_threshold(): void
    {
        $service = $this->serviceWithActivation([
            'status' => Service::STATUS_SUSPENDED,
            'suspended_at' => now()->subMonth(),
        ]);

        Artisan::call('dismantle:queue-overdue-suspensions');

        $service->refresh();
        $this->assertSame(Service::STATUS_SUSPENDED, $service->status);
        $this->assertSame(0, ServiceDismantle::where('service_id', $service->id)->count());
    }

    public function test_does_not_touch_active_or_canceled_services(): void
    {
        $active = $this->serviceWithActivation(['status' => Service::STATUS_ACTIVE]);
        $canceled = Service::factory()->create([
            'status' => Service::STATUS_CANCELED,
            'suspended_at' => now()->subMonths(6),
        ]);

        Artisan::call('dismantle:queue-overdue-suspensions');

        $this->assertSame(Service::STATUS_ACTIVE, $active->fresh()->status);
        $this->assertSame(Service::STATUS_CANCELED, $canceled->fresh()->status);
    }

    public function test_idempotent_when_run_twice(): void
    {
        $service = $this->serviceWithActivation([
            'status' => Service::STATUS_SUSPENDED,
            'suspended_at' => now()->subMonths(3),
        ]);

        Artisan::call('dismantle:queue-overdue-suspensions');
        Artisan::call('dismantle:queue-overdue-suspensions');

        $this->assertSame(1, ServiceDismantle::where('service_id', $service->id)->count());
    }

    public function test_does_not_touch_already_queued_dismantling_or_dismantled_services(): void
    {
        foreach ([Service::STATUS_PENDING_DISMANTLE, Service::STATUS_DISMANTLING, Service::STATUS_DISMANTLED] as $status) {
            $service = Service::factory()->create([
                'status' => $status,
                'suspended_at' => now()->subMonths(6),
            ]);

            Artisan::call('dismantle:queue-overdue-suspensions');

            $this->assertSame($status, $service->fresh()->status);
        }
    }
}
