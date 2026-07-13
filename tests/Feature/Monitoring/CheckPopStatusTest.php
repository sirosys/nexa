<?php

namespace Tests\Feature\Monitoring;

use App\Models\Pop;
use App\Services\Mikrotik\MikrotikGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CapturingMikrotikGateway;
use Tests\TestCase;

class CheckPopStatusTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(): CapturingMikrotikGateway
    {
        $gateway = new CapturingMikrotikGateway;
        $this->app->instance(MikrotikGateway::class, $gateway);

        return $gateway;
    }

    public function test_pop_with_host_marked_online_when_reachable(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->reachable = true;
        $pop = Pop::factory()->create(['host' => '10.0.0.1', 'status' => Pop::STATUS_UNKNOWN, 'last_online_at' => null]);

        $this->artisan('monitoring:check-pop-status')->assertExitCode(0);

        $pop->refresh();
        $this->assertSame(Pop::STATUS_ONLINE, $pop->status);
        $this->assertNotNull($pop->last_online_at);
    }

    public function test_pop_with_host_marked_offline_when_unreachable_and_last_online_at_preserved(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->reachable = false;
        $previouslyOnlineAt = now()->subDay();
        $pop = Pop::factory()->create(['host' => '10.0.0.2', 'status' => Pop::STATUS_ONLINE, 'last_online_at' => $previouslyOnlineAt]);

        $this->artisan('monitoring:check-pop-status')->assertExitCode(0);

        $pop->refresh();
        $this->assertSame(Pop::STATUS_OFFLINE, $pop->status);
        $this->assertEqualsWithDelta(0, $pop->last_online_at->diffInSeconds($previouslyOnlineAt), 1);
    }

    public function test_pop_without_host_is_left_untouched(): void
    {
        $this->fakeGateway();
        $pop = Pop::factory()->create(['host' => null, 'status' => Pop::STATUS_UNKNOWN]);

        $this->artisan('monitoring:check-pop-status')->assertExitCode(0);

        $this->assertSame(Pop::STATUS_UNKNOWN, $pop->fresh()->status);
    }

    public function test_gateway_failure_marks_pop_offline_instead_of_throwing(): void
    {
        $gateway = $this->fakeGateway();
        $gateway->shouldFail = true;
        $pop = Pop::factory()->create(['host' => '10.0.0.3']);

        $this->artisan('monitoring:check-pop-status')->assertExitCode(0);

        $this->assertSame(Pop::STATUS_OFFLINE, $pop->fresh()->status);
    }
}
