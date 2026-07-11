<?php

namespace Tests\Feature\Billing;

use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CancelExpiredInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_cancels_expired_unpaid_invoice_and_its_service(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_PENDING_PAYMENT]);
        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => now()->subDays(4),
            'expired_at' => now()->subDay(),
        ]);
        $receipt = Receipt::factory()->create(['sale_id' => $sale->id, 'status' => 'PENDING']);

        Artisan::call('billing:cancel-expired-invoices');

        $sale->refresh();
        $service->refresh();
        $receipt->refresh();

        $this->assertNotNull($sale->canceled_at);
        $this->assertSame(Service::STATUS_CANCELED, $service->status);
        $this->assertSame('EXPIRED', $receipt->status);
    }

    public function test_command_does_not_touch_invoice_not_yet_expired(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_PENDING_PAYMENT]);
        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => now()->subHour(),
            'expired_at' => now()->addDays(2),
        ]);

        Artisan::call('billing:cancel-expired-invoices');

        $sale->refresh();
        $service->refresh();

        $this->assertNull($sale->canceled_at);
        $this->assertSame(Service::STATUS_PENDING_PAYMENT, $service->status);
    }

    public function test_command_does_not_touch_sale_never_invoiced(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_PENDING_PAYMENT]);
        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => null,
            'expired_at' => now()->subDay(),
        ]);

        Artisan::call('billing:cancel-expired-invoices');

        $sale->refresh();
        $this->assertNull($sale->canceled_at);
    }

    public function test_command_does_not_touch_already_settled_sale(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_ACTIVE]);
        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => now()->subDays(4),
            'expired_at' => now()->subDay(),
            'settled_at' => now()->subDays(3),
        ]);

        Artisan::call('billing:cancel-expired-invoices');

        $sale->refresh();
        $service->refresh();

        $this->assertNull($sale->canceled_at);
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
    }
}
