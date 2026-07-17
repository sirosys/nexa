<?php

namespace Tests\Feature\Monitoring;

use App\Models\Site;
use App\Services\Mikrotik\MikrotikGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CapturingMikrotikGateway;
use Tests\TestCase;

class CheckSiteStatusTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(): CapturingMikrotikGateway
    {
        $gateway = new CapturingMikrotikGateway;
        $this->app->instance(MikrotikGateway::class, $gateway);

        return $gateway;
    }

    public function test_site_with_host_marked_online_when_reachable(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->reachable = true;
        $site = Site::factory()->create(['host' => '10.0.0.1', 'status' => Site::STATUS_UNKNOWN, 'last_online_at' => null]);

        $this->artisan('monitoring:check-site-status')->assertExitCode(0);

        $site->refresh();
        $this->assertSame(Site::STATUS_ONLINE, $site->status);
        $this->assertNotNull($site->last_online_at);
    }

    public function test_site_with_host_marked_offline_when_unreachable_and_last_online_at_preserved(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->reachable = false;
        $previouslyOnlineAt = now()->subDay();
        $site = Site::factory()->create(['host' => '10.0.0.2', 'status' => Site::STATUS_ONLINE, 'last_online_at' => $previouslyOnlineAt]);

        $this->artisan('monitoring:check-site-status')->assertExitCode(0);

        $site->refresh();
        $this->assertSame(Site::STATUS_OFFLINE, $site->status);
        $this->assertEqualsWithDelta(0, $site->last_online_at->diffInSeconds($previouslyOnlineAt), 1);
    }

    public function test_site_without_host_is_left_untouched(): void
    {
        $this->fakeGateway();
        $site = Site::factory()->create(['host' => null, 'status' => Site::STATUS_UNKNOWN]);

        $this->artisan('monitoring:check-site-status')->assertExitCode(0);

        $this->assertSame(Site::STATUS_UNKNOWN, $site->fresh()->status);
    }

    public function test_gateway_failure_marks_site_offline_instead_of_throwing(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->shouldFail = true;
        $site = Site::factory()->create(['host' => '10.0.0.3']);

        $this->artisan('monitoring:check-site-status')->assertExitCode(0);

        $this->assertSame(Site::STATUS_OFFLINE, $site->fresh()->status);
    }
}
