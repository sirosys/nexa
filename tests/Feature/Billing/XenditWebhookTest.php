<?php

namespace Tests\Feature\Billing;

use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Notifications\PaymentReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class XenditWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.xendit.webhook_token', 'test-webhook-token');
    }

    private function receiptForPendingSale(): Receipt
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $service = Service::factory()->create([
            'user_id' => $customer->id,
            'status' => Service::STATUS_PENDING_PAYMENT,
        ]);

        $sale = Sale::factory()->create([
            'service_id' => $service->id,
            'grandtotal' => 150000,
            'invoiced_at' => now()->subHour(),
            'expired_at' => now()->addDays(2),
        ]);

        return Receipt::factory()->create([
            'sale_id' => $sale->id,
            'amount' => 150000,
            'status' => 'PENDING',
            'xendit_payment_request_id' => 'pr-webhook-test-1',
        ]);
    }

    public function test_valid_webhook_marks_sale_paid_and_service_pending_installation(): void
    {
        Notification::fake();

        $receipt = $this->receiptForPendingSale();

        $response = $this->postJson('/webhooks/xendit', [
            'data' => [
                'id' => $receipt->xendit_payment_request_id,
                'status' => 'SUCCEEDED',
            ],
        ], ['x-callback-token' => 'test-webhook-token']);

        $response->assertOk();

        $receipt->refresh();
        $this->assertSame('SUCCEEDED', $receipt->status);

        $sale = $receipt->sale;
        $this->assertNotNull($sale->settled_at);

        $service = $sale->service->fresh();
        $this->assertSame(Service::STATUS_PENDING_INSTALLATION, $service->status);

        Notification::assertSentTo($service->user, PaymentReceivedNotification::class);
    }

    public function test_webhook_with_invalid_token_is_rejected(): void
    {
        $receipt = $this->receiptForPendingSale();

        $response = $this->postJson('/webhooks/xendit', [
            'data' => [
                'id' => $receipt->xendit_payment_request_id,
                'status' => 'SUCCEEDED',
            ],
        ], ['x-callback-token' => 'wrong-token']);

        $response->assertForbidden();

        $receipt->refresh();
        $this->assertSame('PENDING', $receipt->status);
        $this->assertNull($receipt->sale->settled_at);
    }

    public function test_unknown_payment_request_id_is_ignored_with_200(): void
    {
        // Xendit mewajibkan 2xx untuk setiap percobaan webhook (termasuk
        // tombol "Test Webhook" dashboard, yang mengirim payment_request_id
        // dummy) - non-2xx berulang membuat Xendit menandai endpoint
        // bermasalah. payment_request_id tak dikenal tetap diabaikan
        // (bukan diproses), cuma status HTTP-nya 200, bukan 404.
        $response = $this->postJson('/webhooks/xendit', [
            'data' => [
                'id' => 'pr-does-not-exist',
                'status' => 'SUCCEEDED',
            ],
        ], ['x-callback-token' => 'test-webhook-token']);

        $response->assertOk();
    }

    public function test_unrelated_event_type_without_payment_request_id_is_ignored_with_200(): void
    {
        // Contoh payload Payment Token (data.payment_token_id, bukan
        // payment_request_id) - dikirim Xendit ke webhook URL yang sama
        // saat user test kategori "Payment Token" di dashboard. NEXA cuma
        // berlangganan Payment Requests API v3, jadi event ini tidak
        // relevan dan harus diabaikan dengan 200, bukan error.
        $response = $this->postJson('/webhooks/xendit', [
            'event' => 'payment_token.activation',
            'data' => [
                'payment_token_id' => 'pt-does-not-matter',
                'status' => 'ACTIVE',
            ],
        ], ['x-callback-token' => 'test-webhook-token']);

        $response->assertOk();
    }

    public function test_duplicate_succeeded_webhook_is_idempotent(): void
    {
        Notification::fake();

        $receipt = $this->receiptForPendingSale();

        $payload = [
            'data' => [
                'id' => $receipt->xendit_payment_request_id,
                'status' => 'SUCCEEDED',
            ],
        ];
        $headers = ['x-callback-token' => 'test-webhook-token'];

        $this->postJson('/webhooks/xendit', $payload, $headers)->assertOk();
        $this->postJson('/webhooks/xendit', $payload, $headers)->assertOk();

        $service = $receipt->sale->service->fresh();

        Notification::assertSentToTimes($service->user, PaymentReceivedNotification::class, 1);
    }
}
