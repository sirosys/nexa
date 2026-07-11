<?php

namespace Tests\Feature\Billing;

use App\Models\Receipt;
use App\Models\Sale;
use App\Models\Service;
use App\Models\User;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class PaymentOtpVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function pendingReceipt(): Receipt
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

        $receipt = Receipt::create([
            'sale_id' => $sale->id,
            'code' => 'REC'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
            'amount' => 150000,
            'status' => Receipt::STATUS_AWAITING_CHANNEL_SELECTION,
        ]);

        $receipt->update([
            'checkout_url' => URL::temporarySignedRoute('payment.show', now()->addDays(3), ['receipt' => $receipt->id]),
        ]);

        return $receipt->fresh();
    }

    private function fakeWhatsappGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    private function extractCode(string $message): string
    {
        preg_match('/(\d{6})/', $message, $matches);

        return $matches[1];
    }

    public function test_unverified_receipt_shows_otp_screen_and_sends_code(): void
    {
        $gateway = $this->fakeWhatsappGateway();
        $receipt = $this->pendingReceipt();

        $response = $this->get($receipt->checkout_url);

        $response->assertOk();
        $response->assertSee('Verifikasi Pembayaran');
        $response->assertDontSee('Pilih metode pembayaran');
        $this->assertNotNull($gateway->message);
        $this->assertSame((string) $receipt->sale->service->user->phone, $gateway->phone);
        $this->assertDatabaseCount('receipt_otp_codes', 1);
    }

    public function test_reloading_page_does_not_send_duplicate_code_while_pending(): void
    {
        $gateway = $this->fakeWhatsappGateway();
        $receipt = $this->pendingReceipt();

        $this->get($receipt->checkout_url);
        $this->get($receipt->checkout_url);

        $this->assertDatabaseCount('receipt_otp_codes', 1);
    }

    public function test_correct_code_unlocks_channel_picker(): void
    {
        $gateway = $this->fakeWhatsappGateway();
        $receipt = $this->pendingReceipt();

        $this->get($receipt->checkout_url);
        $code = $this->extractCode($gateway->message);

        $this->post($receipt->checkout_url, ['code' => $code]);

        $response = $this->get($receipt->checkout_url);
        $response->assertSee('Pilih metode pembayaran');
    }

    public function test_wrong_code_increments_attempts_and_shows_error(): void
    {
        $gateway = $this->fakeWhatsappGateway();
        $receipt = $this->pendingReceipt();

        $this->get($receipt->checkout_url);

        $response = $this->post($receipt->checkout_url, ['code' => '000000']);

        $response->assertRedirect($receipt->checkout_url);
        $response->assertSessionHas('otp_error');

        $otp = $receipt->otpCodes()->first();
        $this->assertSame(1, $otp->attempts);
    }

    public function test_lockout_after_max_attempts(): void
    {
        $gateway = $this->fakeWhatsappGateway();
        $receipt = $this->pendingReceipt();

        $this->get($receipt->checkout_url);
        $code = $this->extractCode($gateway->message);

        // config('payment_otp.max_attempts') default = 5.
        for ($i = 0; $i < 5; $i++) {
            $this->post($receipt->checkout_url, ['code' => '000000']);
        }

        // Kode yang benar pun sekarang ditolak karena sudah lockout.
        $response = $this->post($receipt->checkout_url, ['code' => $code]);

        $response->assertSessionHas('otp_error');

        $getResponse = $this->get($receipt->checkout_url);
        $getResponse->assertDontSee('Pilih metode pembayaran');
    }

    public function test_channel_selection_blocked_before_otp_verified(): void
    {
        $this->fakeWhatsappGateway();
        $receipt = $this->pendingReceipt();

        $this->get($receipt->checkout_url);

        $response = $this->post($receipt->checkout_url, ['channel_code' => 'QRIS']);

        $response->assertRedirect($receipt->checkout_url);
        $receipt->refresh();
        $this->assertNull($receipt->channel_code);
    }

    public function test_resend_respects_cooldown(): void
    {
        $gateway = $this->fakeWhatsappGateway();
        $receipt = $this->pendingReceipt();

        $this->get($receipt->checkout_url);

        $response = $this->post($receipt->checkout_url, ['resend_otp' => '1']);

        $response->assertSessionHas('otp_error');
        $this->assertDatabaseCount('receipt_otp_codes', 1);
    }

    public function test_verification_expires_after_grace_period(): void
    {
        $gateway = $this->fakeWhatsappGateway();
        $receipt = $this->pendingReceipt();

        $this->get($receipt->checkout_url);
        $code = $this->extractCode($gateway->message);
        $this->post($receipt->checkout_url, ['code' => $code]);

        $this->get($receipt->checkout_url)->assertSee('Pilih metode pembayaran');

        Carbon::setTestNow(now()->addMinutes((int) config('payment_otp.verified_grace_minutes') + 1));

        try {
            $response = $this->get($receipt->checkout_url);
            $response->assertSee('Verifikasi Pembayaran');
            $response->assertDontSee('Pilih metode pembayaran');
        } finally {
            Carbon::setTestNow();
        }
    }
}
