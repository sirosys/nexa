<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CapturingWhatsappGateway;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    private function requestOtp(CapturingWhatsappGateway $gateway, string $phone = '81111111111'): array
    {
        $response = $this->postJson('/api/v1/auth/register/request-otp', ['phone' => $phone]);

        return [
            'registration_token' => $response->json('registration_token'),
            'code' => $gateway->code,
        ];
    }

    public function test_already_registered_phone_is_rejected_at_request_otp_step(): void
    {
        $gateway = $this->fakeGateway();
        User::factory()->create(['phone' => '6281111111111']);

        $response = $this->postJson('/api/v1/auth/register/request-otp', ['phone' => '81111111111']);

        $response->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->assertNull($gateway->code);
    }

    public function test_new_phone_receives_otp_and_registration_token(): void
    {
        $gateway = $this->fakeGateway();

        $response = $this->postJson('/api/v1/auth/register/request-otp', ['phone' => '81111111111']);

        $response->assertStatus(202)->assertJsonStructure(['message', 'registration_token']);
        $this->assertSame('6281111111111', $gateway->phone);
        $this->assertNotNull($gateway->code);
    }

    public function test_correct_code_creates_customer_account_and_returns_token(): void
    {
        $gateway = $this->fakeGateway();
        $challenge = $this->requestOtp($gateway);

        $response = $this->postJson('/api/v1/auth/register', [
            'registration_token' => $challenge['registration_token'],
            'code' => $challenge['code'],
            'name' => 'pelanggan baru',
            'email' => 'pelanggan.baru@example.com',
        ]);

        $response->assertStatus(201)->assertJsonStructure(['token', 'user' => ['code', 'name', 'phone', 'email']]);
        $response->assertJsonPath('user.name', 'Pelanggan Baru'); // TitleCase
        $response->assertJsonPath('user.phone', '6281111111111');
        $response->assertJsonPath('user.nik', null);

        $user = User::where('phone', '6281111111111')->firstOrFail();
        $this->assertTrue($user->isCustomer());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $user->id,
            'type' => UserRegisteredNotification::class,
        ]);

        // Token yang dikembalikan benar-benar bisa dipakai.
        $meResponse = $this->withHeader('Authorization', 'Bearer '.$response->json('token'))
            ->getJson('/api/v1/me');
        $meResponse->assertOk()->assertJsonPath('data.phone', '6281111111111');
    }

    public function test_wrong_code_is_rejected_and_does_not_create_account(): void
    {
        $gateway = $this->fakeGateway();
        $challenge = $this->requestOtp($gateway);

        $response = $this->postJson('/api/v1/auth/register', [
            'registration_token' => $challenge['registration_token'],
            'code' => '000000',
            'name' => 'Pelanggan Baru',
            'email' => 'pelanggan.baru@example.com',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['phone' => '6281111111111']);
    }

    public function test_registration_token_cannot_be_reused_after_success(): void
    {
        $gateway = $this->fakeGateway();
        $challenge = $this->requestOtp($gateway);

        $payload = [
            'registration_token' => $challenge['registration_token'],
            'code' => $challenge['code'],
            'name' => 'Pelanggan Baru',
            'email' => 'pelanggan.baru@example.com',
        ];

        $this->postJson('/api/v1/auth/register', $payload)->assertStatus(201);

        $second = $this->postJson('/api/v1/auth/register', [
            ...$payload,
            'email' => 'pelanggan.lain@example.com',
        ]);

        $second->assertStatus(422);
    }

    public function test_invalid_or_unknown_registration_token_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'registration_token' => 'bogus',
            'code' => '123456',
            'name' => 'Pelanggan Baru',
            'email' => 'pelanggan.baru@example.com',
        ]);

        $response->assertStatus(422);
    }

    public function test_duplicate_email_is_rejected_without_burning_the_otp_code(): void
    {
        $gateway = $this->fakeGateway();
        User::factory()->create(['email' => 'sudah.ada@example.com']);
        $challenge = $this->requestOtp($gateway);

        $firstAttempt = $this->postJson('/api/v1/auth/register', [
            'registration_token' => $challenge['registration_token'],
            'code' => $challenge['code'],
            'name' => 'Pelanggan Baru',
            'email' => 'sudah.ada@example.com',
        ]);
        $firstAttempt->assertStatus(422)->assertJsonValidationErrors('email');

        // Validasi email gagal SEBELUM kode OTP sempat diverifikasi/dikonsumsi
        // (FormRequest divalidasi sebelum method controller jalan) — kode yang
        // sama masih bisa dipakai ulang dengan email yang benar.
        $secondAttempt = $this->postJson('/api/v1/auth/register', [
            'registration_token' => $challenge['registration_token'],
            'code' => $challenge['code'],
            'name' => 'Pelanggan Baru',
            'email' => 'pelanggan.baru@example.com',
        ]);
        $secondAttempt->assertStatus(201);
    }

    public function test_resend_cooldown_returns_429(): void
    {
        $this->fakeGateway();

        $this->postJson('/api/v1/auth/register/request-otp', ['phone' => '81111111111'])->assertStatus(202);

        $response = $this->postJson('/api/v1/auth/register/request-otp', ['phone' => '81111111111']);

        $response->assertStatus(429);
    }

    public function test_register_throttle_limiter_returns_429(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $response = $this->postJson('/api/v1/auth/register', [
                'registration_token' => 'bogus',
                'code' => '000000',
                'name' => 'Pelanggan Baru',
                'email' => 'pelanggan'.$i.'@example.com',
            ]);
        }

        $response->assertStatus(429);
    }
}
