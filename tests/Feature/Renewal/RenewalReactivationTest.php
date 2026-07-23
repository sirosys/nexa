<?php

namespace Tests\Feature\Renewal;

use App\Models\Package;
use App\Models\Plan;
use App\Models\Receipt;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\ServiceReactivatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RenewalReactivationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.xendit.webhook_token', 'test-webhook-token');
    }

    private function sendWebhook(Receipt $receipt): TestResponse
    {
        return $this->postJson('/webhooks/xendit', [
            'data' => [
                'id' => $receipt->xendit_payment_request_id,
                'status' => 'SUCCEEDED',
            ],
        ], ['x-callback-token' => 'test-webhook-token']);
    }

    /**
     * Skenario reaktivasi sungguhan: Service sudah suspended, pelanggan
     * bayar telat. expired_at HARUS dihitung dari nilai LAMA (bukan now()),
     * meski waktu pembayaran sudah jauh melewati expired_at lama itu.
     */
    public function test_late_payment_while_suspended_reactivates_and_extends_from_old_expiry(): void
    {
        Notification::fake();

        $package = Package::factory()->create();
        $oldExpiredAt = now()->subDays(20); // jauh terlewat, simulasi telat bayar
        $service = Service::factory()->create([
            'status' => Service::STATUS_SUSPENDED,
            'package_id' => $package->id,
            'expired_at' => $oldExpiredAt,
            'suspended_at' => $oldExpiredAt->copy()->addDay(),
        ]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'package_id' => $package->id,
            'is_renewal' => true,
            'grandtotal' => 150000,
            'invoiced_at' => $oldExpiredAt->copy()->subDays(5),
            'expired_at' => null,
        ]);
        $receipt = Receipt::factory()->create([
            'service_order_id' => $serviceOrder->id,
            'amount' => 150000,
            'status' => 'PENDING',
            'xendit_payment_request_id' => 'pr-renewal-late-1',
        ]);

        $this->sendWebhook($receipt)->assertOk();

        $serviceOrder->refresh();
        $service->refresh();

        $this->assertNotNull($serviceOrder->settled_at);
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
        $this->assertNull($service->suspended_at);
        $this->assertSame(
            $oldExpiredAt->copy()->addMonths(1)->format('Y-m-d H:i:s'),
            $service->expired_at->format('Y-m-d H:i:s'),
        );

        Notification::assertSentTo($service->user, ServiceReactivatedNotification::class);
        Notification::assertNotSentTo($service->user, PaymentReceivedNotification::class);
    }

    /**
     * Skenario pembayaran tepat waktu: Service masih active (belum sempat
     * suspend). reactivate() harus tetap jalan seragam (bukan no-op),
     * memperpanjang expired_at dari nilai lama yang sama.
     */
    public function test_on_time_payment_while_still_active_extends_expiry_uniformly(): void
    {
        Notification::fake();

        $package = Package::factory()->create();
        $oldExpiredAt = now()->addDays(2); // belum lewat, service masih active
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => $oldExpiredAt,
        ]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'package_id' => $package->id,
            'is_renewal' => true,
            'grandtotal' => 150000,
            'invoiced_at' => now()->subDays(2),
            'expired_at' => null,
        ]);
        $receipt = Receipt::factory()->create([
            'service_order_id' => $serviceOrder->id,
            'amount' => 150000,
            'status' => 'PENDING',
            'xendit_payment_request_id' => 'pr-renewal-ontime-1',
        ]);

        $this->sendWebhook($receipt)->assertOk();

        $service->refresh();
        $this->assertSame(Service::STATUS_ACTIVE, $service->status);
        $this->assertNull($service->suspended_at);
        $this->assertSame(
            $oldExpiredAt->copy()->addMonths(1)->format('Y-m-d H:i:s'),
            $service->expired_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_duplicate_webhook_does_not_extend_expiry_twice(): void
    {
        Notification::fake();

        $package = Package::factory()->create();
        $oldExpiredAt = now()->subDays(3);
        $service = Service::factory()->create([
            'status' => Service::STATUS_SUSPENDED,
            'package_id' => $package->id,
            'expired_at' => $oldExpiredAt,
            'suspended_at' => $oldExpiredAt->copy()->addDay(),
        ]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'package_id' => $package->id,
            'is_renewal' => true,
            'grandtotal' => 150000,
            'invoiced_at' => $oldExpiredAt->copy()->subDays(5),
            'expired_at' => null,
        ]);
        $receipt = Receipt::factory()->create([
            'service_order_id' => $serviceOrder->id,
            'amount' => 150000,
            'status' => 'PENDING',
            'xendit_payment_request_id' => 'pr-renewal-dup-1',
        ]);

        $this->sendWebhook($receipt)->assertOk();
        $expiredAtAfterFirst = $service->fresh()->expired_at;

        $this->sendWebhook($receipt)->assertOk();
        $expiredAtAfterSecond = $service->fresh()->expired_at;

        $this->assertTrue($expiredAtAfterFirst->equalTo($expiredAtAfterSecond));
        Notification::assertSentToTimes($service->user, ServiceReactivatedNotification::class, 1);
    }

    /**
     * Durasi perpanjangan dibaca dari service_orders.plan_qty ITU SENDIRI (bukan
     * package->plan_qty) — nilainya SELALU 1 untuk renewal otomatis saat
     * ini, tapi ditulis generik supaya siap dipakai perpanjangan non-default
     * (qty > 1, mis. promo prepay beberapa bulan) begitu customer app/API
     * dibangun. Test ini membuktikan mekanismenya sudah benar untuk qty > 1.
     */
    public function test_extends_expiry_by_service_order_line_item_quantity_not_fixed_one_month(): void
    {
        Notification::fake();

        $plan = Plan::factory()->create(['price' => 150000]);
        $package = Package::factory()->create(['plan_id' => $plan->id, 'plan_qty' => 1]);
        $oldExpiredAt = now()->addDays(2);
        $service = Service::factory()->create([
            'status' => Service::STATUS_ACTIVE,
            'package_id' => $package->id,
            'expired_at' => $oldExpiredAt,
        ]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'package_id' => $package->id,
            'is_renewal' => true,
            'plan_id' => $plan->id,
            'plan_price' => 150000,
            'plan_qty' => 3,
            'grandtotal' => 450000,
            'invoiced_at' => now()->subDays(2),
            'expired_at' => null,
        ]);
        $receipt = Receipt::factory()->create([
            'service_order_id' => $serviceOrder->id,
            'amount' => 450000,
            'status' => 'PENDING',
            'xendit_payment_request_id' => 'pr-renewal-bulk-1',
        ]);

        $this->sendWebhook($receipt)->assertOk();

        $service->refresh();
        $this->assertSame(
            $oldExpiredAt->copy()->addMonths(3)->format('Y-m-d H:i:s'),
            $service->expired_at->format('Y-m-d H:i:s'),
        );
    }

    /**
     * Regression: Order Layanan registrasi (is_renewal=false) tetap lewat jalur
     * pending_installation lama, tidak tersentuh cabang renewal sama sekali.
     */
    public function test_registration_service_order_still_goes_through_pending_installation_path(): void
    {
        Notification::fake();

        $service = Service::factory()->create(['status' => Service::STATUS_PENDING_PAYMENT]);
        $serviceOrder = ServiceOrder::factory()->create([
            'service_id' => $service->id,
            'is_renewal' => false,
            'grandtotal' => 150000,
            'invoiced_at' => now()->subHour(),
            'expired_at' => now()->addDays(2),
        ]);
        $receipt = Receipt::factory()->create([
            'service_order_id' => $serviceOrder->id,
            'amount' => 150000,
            'status' => 'PENDING',
            'xendit_payment_request_id' => 'pr-registration-1',
        ]);

        $this->sendWebhook($receipt)->assertOk();

        $service->refresh();
        $this->assertSame(Service::STATUS_PENDING_INSTALLATION, $service->status);
        Notification::assertSentTo($service->user, PaymentReceivedNotification::class);
        Notification::assertNotSentTo($service->user, ServiceReactivatedNotification::class);
    }
}
