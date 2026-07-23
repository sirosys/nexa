<?php

namespace Tests\Feature\Billing;

use App\Models\Receipt;
use App\Models\Service;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CancelExpiredInvoicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_cancels_expired_unpaid_invoice_and_its_service(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_PENDING_PAYMENT]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => now()->subDays(4),
            'expired_at' => now()->subDay(),
        ]);
        $receipt = Receipt::factory()->create(['service_order_id' => $serviceOrder->id, 'status' => 'PENDING']);

        Artisan::call('billing:cancel-expired-invoices');

        $serviceOrder->refresh();
        $service->refresh();
        $receipt->refresh();

        $this->assertNotNull($serviceOrder->canceled_at);
        $this->assertSame(Service::STATUS_CANCELED, $service->status);
        $this->assertSame('EXPIRED', $receipt->status);
    }

    public function test_command_does_not_touch_invoice_not_yet_expired(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_PENDING_PAYMENT]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => now()->subHour(),
            'expired_at' => now()->addDays(2),
        ]);

        Artisan::call('billing:cancel-expired-invoices');

        $serviceOrder->refresh();
        $service->refresh();

        $this->assertNull($serviceOrder->canceled_at);
        $this->assertSame(Service::STATUS_PENDING_PAYMENT, $service->status);
    }

    public function test_command_does_not_touch_service_order_never_invoiced(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_PENDING_PAYMENT]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => null,
            'expired_at' => now()->subDay(),
        ]);

        Artisan::call('billing:cancel-expired-invoices');

        $serviceOrder->refresh();
        $this->assertNull($serviceOrder->canceled_at);
    }

    public function test_command_does_not_touch_already_settled_service_order(): void
    {
        $service = Service::factory()->create(['status' => Service::STATUS_ACTIVE]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'invoiced_at' => now()->subDays(4),
            'expired_at' => now()->subDay(),
            'settled_at' => now()->subDays(3),
        ]);

        Artisan::call('billing:cancel-expired-invoices');

        $serviceOrder->refresh();
        $service->refresh();

        $this->assertNull($serviceOrder->canceled_at);
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
    }

    /**
     * Regression kunci modul Renewal (lihat CLAUDE.md "Renewal"): Order
     * Layanan renewal sengaja tidak pernah diberi expired_at oleh
     * ReceiptService::createForServiceOrder() — jadi command ini (yang cuma
     * untuk tagihan pendaftaran) tidak boleh pernah menyentuhnya, walau
     * service.expired_at sudah lama lewat dan Order Layanan-nya belum
     * dibayar. Kalau ini gagal, berarti konflik desain "renewal Order
     * Layanan ke-cancel salah jadi canceled, bukan suspended" sudah kembali
     * muncul.
     */
    public function test_command_never_touches_unpaid_renewal_service_order(): void
    {
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'expired_at' => now()->subDays(10),
        ]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'is_renewal' => true,
            'invoiced_at' => now()->subDays(10),
            'expired_at' => null,
        ]);

        Artisan::call('billing:cancel-expired-invoices');

        $serviceOrder->refresh();
        $service->refresh();

        $this->assertNull($serviceOrder->canceled_at);
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
    }
}
