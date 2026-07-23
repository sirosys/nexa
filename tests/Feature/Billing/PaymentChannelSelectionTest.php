<?php

namespace Tests\Feature\Billing;

use App\Models\Receipt;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Services\Billing\XenditGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Support\FakeXenditGateway;
use Tests\TestCase;

class PaymentChannelSelectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifikasi OTP (lihat PaymentOtpVerificationTest untuk test gate-nya
     * sendiri) sudah dianggap "lulus" di sini secara default supaya test
     * di file ini fokus murni ke perilaku pilih channel, bukan OTP.
     */
    private function pendingReceipt(array $serviceOrderAttributes = [], bool $otpVerified = true): Receipt
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $service = Service::factory()->create([
            'user_id' => $customer->id,
            'status' => Service::STATUS_PENDING_PAYMENT,
        ]);

        $serviceOrder = ServiceOrder::factory()->create(array_merge([
            'service_id' => $service->id,
            'grandtotal' => 150000,
            'invoiced_at' => now()->subHour(),
            'expired_at' => now()->addDays(2),
        ], $serviceOrderAttributes));

        $receipt = Receipt::create([
            'service_order_id' => $serviceOrder->id,
            'code' => 'REC'.str_pad((string) $serviceOrder->id, 6, '0', STR_PAD_LEFT),
            'amount' => 150000,
            'status' => Receipt::STATUS_AWAITING_CHANNEL_SELECTION,
        ]);

        $receipt->update([
            'checkout_url' => URL::temporarySignedRoute('payment.show', now()->addDays(3), ['receipt' => $receipt->id]),
        ]);

        if ($otpVerified) {
            $receipt->otpCodes()->create([
                'code_hash' => 'n/a',
                'expires_at' => now()->addMinutes(5),
                'consumed_at' => now(),
            ]);
        }

        return $receipt->fresh();
    }

    public function test_picker_page_renders_channel_groups(): void
    {
        $receipt = $this->pendingReceipt();

        $response = $this->get($receipt->checkout_url);

        $response->assertOk();
        $response->assertSee('QRIS');
        $response->assertSee('Virtual Account');
        $response->assertSee('Retail');
    }

    public function test_selecting_qris_sends_empty_channel_properties(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $receipt = $this->pendingReceipt();

        $response = $this->post($receipt->checkout_url, ['channel_code' => 'QRIS']);

        $response->assertRedirect($receipt->checkout_url);
        $this->assertCount(1, $fake->calls);
        $this->assertSame('QRIS', $fake->calls[0]['channelCode']);
        $this->assertSame([], $fake->calls[0]['channelProperties']);
        $this->assertSame('PAY', $fake->calls[0]['type']);

        $receipt->refresh();
        $this->assertSame('QRIS', $receipt->channel_code);
        $this->assertNotNull($receipt->xendit_payment_request_id);
    }

    /**
     * Virtual Account diperbaiki (type=REUSABLE_PAYMENT_CODE + channel_code
     * bersuffix "_VIRTUAL_ACCOUNT") setelah percobaan pertama (type=PAY,
     * channel_code polos) ditolak Xendit — lihat CLAUDE.md "Billing /
     * Invoice (Xendit)". Cuma 3 bank yang ditawarkan (BCA/Mandiri/BRI),
     * sesuai permintaan eksplisit user.
     */
    public function test_selecting_virtual_account_uses_reusable_payment_code_type(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $receipt = $this->pendingReceipt();
        $customerName = $receipt->serviceOrder->service->user->name;

        $response = $this->post($receipt->checkout_url, ['channel_code' => 'BCA_VIRTUAL_ACCOUNT']);

        $response->assertRedirect($receipt->checkout_url);
        $this->assertCount(1, $fake->calls);
        $this->assertSame('BCA_VIRTUAL_ACCOUNT', $fake->calls[0]['channelCode']);
        $this->assertSame('REUSABLE_PAYMENT_CODE', $fake->calls[0]['type']);
        $this->assertSame(['display_name' => $customerName], $fake->calls[0]['channelProperties']);

        $receipt->refresh();
        $this->assertSame('BCA_VIRTUAL_ACCOUNT', $receipt->channel_code);
        $this->assertNotNull($receipt->xendit_payment_request_id);
    }

    public function test_selecting_retail_outlet_sends_payer_name(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $receipt = $this->pendingReceipt();
        $customerName = $receipt->serviceOrder->service->user->name;

        $response = $this->post($receipt->checkout_url, ['channel_code' => 'INDOMARET']);

        $this->assertSame('INDOMARET', $fake->calls[0]['channelCode']);
        $this->assertSame('PAY', $fake->calls[0]['type']);
        $this->assertSame(['payer_name' => $customerName], $fake->calls[0]['channelProperties']);
    }

    public function test_invalid_channel_code_is_rejected(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $receipt = $this->pendingReceipt();

        $response = $this->post($receipt->checkout_url, ['channel_code' => 'NOT_A_REAL_CHANNEL']);

        $response->assertSessionHasErrors('channel_code');
        $this->assertCount(0, $fake->calls);
    }

    /**
     * E-Wallet dihapus total dari daftar channel atas permintaan eksplisit
     * user (dulu OVO/DANA/SHOPEEPAY/LINKAJA sukses diuji ke sandbox) —
     * memastikan channel lama itu sudah tidak diterima lagi.
     */
    public function test_ewallet_channel_no_longer_accepted(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $receipt = $this->pendingReceipt();

        $response = $this->post($receipt->checkout_url, ['channel_code' => 'OVO']);

        $response->assertSessionHasErrors('channel_code');
        $this->assertCount(0, $fake->calls);
    }

    public function test_tampered_signed_link_is_rejected(): void
    {
        $receipt = $this->pendingReceipt();

        $response = $this->get($receipt->checkout_url.'-tampered');

        $response->assertForbidden();
    }

    public function test_expired_signed_link_is_rejected(): void
    {
        $receipt = $this->pendingReceipt();

        $expiredUrl = URL::temporarySignedRoute('payment.show', now()->subMinute(), ['receipt' => $receipt->id]);

        $response = $this->get($expiredUrl);

        $response->assertForbidden();
    }

    public function test_settled_service_order_shows_final_message_instead_of_picker(): void
    {
        $receipt = $this->pendingReceipt(['settled_at' => now()]);

        $response = $this->get($receipt->checkout_url);

        $response->assertOk();
        $response->assertSee('kami terima');
        $response->assertDontSee('name="channel_code"', false);
    }

    public function test_canceled_service_order_shows_final_message_instead_of_picker(): void
    {
        $receipt = $this->pendingReceipt(['canceled_at' => now()]);

        $response = $this->get($receipt->checkout_url);

        $response->assertOk();
        $response->assertSee('dibatalkan');
        $response->assertDontSee('name="channel_code"', false);
    }

    public function test_channel_cannot_be_switched_after_payment_request_id_exists(): void
    {
        $fake = new FakeXenditGateway;
        $this->app->instance(XenditGateway::class, $fake);

        $receipt = $this->pendingReceipt();

        $this->post($receipt->checkout_url, ['channel_code' => 'QRIS']);
        $this->assertCount(1, $fake->calls);

        $response = $this->post($receipt->checkout_url, ['channel_code' => 'INDOMARET']);

        $response->assertRedirect($receipt->checkout_url);
        $response->assertSessionHas('payment_error');
        // Percobaan kedua tidak menambah panggilan gateway baru.
        $this->assertCount(1, $fake->calls);

        $receipt->refresh();
        $this->assertSame('QRIS', $receipt->channel_code);
    }
}
