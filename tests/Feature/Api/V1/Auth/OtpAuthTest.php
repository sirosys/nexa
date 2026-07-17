<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class OtpAuthTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    private function customer(string $phone = '6281111111111'): User
    {
        $user = User::factory()->create(['phone' => $phone]);
        $user->assignRole('customer');

        return $user;
    }

    public function test_registered_customer_can_request_otp_and_receives_verification_token(): void
    {
        $gateway = $this->fakeGateway();
        $this->customer();

        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111']);

        $response->assertStatus(202)->assertJsonStructure(['message', 'verification_token']);
        $this->assertSame('6281111111111', $gateway->phone);
        $this->assertNotNull($gateway->code);
    }

    public function test_staff_phone_is_rejected(): void
    {
        $gateway = $this->fakeGateway();
        $staff = User::factory()->create(['phone' => '6282222222222']);
        $staff->assignRole('superadmin');

        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '82222222222']);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->assertNull($gateway->code);
    }

    public function test_unregistered_phone_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '89999999999']);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_correct_code_returns_sanctum_token_usable_for_authenticated_requests(): void
    {
        $gateway = $this->fakeGateway();
        $user = $this->customer();
        $requestResponse = $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111']);
        $verificationToken = $requestResponse->json('verification_token');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'verification_token' => $verificationToken,
            'code' => $gateway->code,
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['code', 'name', 'phone']]);

        $meResponse = $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/v1/me');

        $meResponse->assertOk()->assertJsonPath('data.phone', (string) $user->phone);
    }

    public function test_wrong_code_increments_attempts_and_returns_422(): void
    {
        $this->fakeGateway();
        $user = $this->customer();
        $requestResponse = $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111']);
        $verificationToken = $requestResponse->json('verification_token');

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'verification_token' => $verificationToken,
            'code' => '000000',
        ]);

        $response->assertStatus(422);
        $this->assertSame(1, OtpCode::where('user_id', $user->id)->first()->attempts);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $gateway = $this->fakeGateway();
        $user = $this->customer();
        $requestResponse = $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111']);
        $verificationToken = $requestResponse->json('verification_token');

        OtpCode::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'verification_token' => $verificationToken,
            'code' => $gateway->code,
        ]);

        $response->assertStatus(422);
    }

    public function test_invalid_or_unknown_verification_token_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'verification_token' => 'not-a-real-token',
            'code' => '123456',
        ]);

        $response->assertStatus(422);
    }

    public function test_verification_token_cannot_be_reused_after_success(): void
    {
        $gateway = $this->fakeGateway();
        $this->customer();
        $requestResponse = $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111']);
        $verificationToken = $requestResponse->json('verification_token');

        $this->postJson('/api/v1/auth/otp/verify', [
            'verification_token' => $verificationToken,
            'code' => $gateway->code,
        ])->assertOk();

        // Kode sudah consumed (OtpService::verify menandai consumed_at),
        // token verifikasi juga sudah di-forget — percobaan verify kedua
        // dengan token yang sama harus ditolak, bukan diam-diam sukses lagi.
        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'verification_token' => $verificationToken,
            'code' => $gateway->code,
        ]);

        $response->assertStatus(422);
    }

    public function test_resend_cooldown_returns_429_not_500(): void
    {
        $this->fakeGateway();
        $this->customer();

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111'])->assertStatus(202);

        // Diminta lagi segera (masih dalam cooldown resend, lihat
        // config('otp.resend_cooldown_seconds')) — OtpThrottledException
        // wajib ditangkap manual (bukan RuntimeException dengan render()
        // otomatis, lihat CLAUDE.md "Billing / Invoice (Xendit)" pola sama).
        $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111']);

        $response->assertStatus(429);
    }

    public function test_otp_request_throttle_limiter_returns_429(): void
    {
        $this->fakeGateway();
        $this->customer();

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111']);
        }

        // Bucket api-otp-request: 3/menit per ip+phone — baik lewat cooldown
        // resend maupun rate limiter itu sendiri, request berulang cepat
        // wajib berujung 429, tidak pernah 500.
        $response->assertStatus(429);
    }

    public function test_otp_verify_throttle_limiter_returns_429(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/v1/auth/otp/verify', [
                'verification_token' => 'bogus',
                'code' => '000000',
            ]);
        }

        $response->assertStatus(429);
    }

    public function test_logout_revokes_token(): void
    {
        $gateway = $this->fakeGateway();
        $this->customer();
        $requestResponse = $this->postJson('/api/v1/auth/otp/request', ['phone' => '81111111111']);
        $verify = $this->postJson('/api/v1/auth/otp/verify', [
            'verification_token' => $requestResponse->json('verification_token'),
            'code' => $gateway->code,
        ]);
        $token = $verify->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // AuthManager memoize guard 'sanctum' (RequestGuard meng-cache user
        // resolved-nya) selama masih dalam SATU test method — artefak test
        // client, bukan perilaku produksi (tiap request sungguhan selalu
        // proses baru). forgetGuards() supaya request berikutnya benar2
        // re-resolve dari token (yang sudah dihapus), bukan pakai cache.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    public function test_unauthenticated_request_to_protected_route_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertStatus(401);
    }
}
