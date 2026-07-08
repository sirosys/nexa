<?php

namespace Tests\Feature\Auth;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Whatsapp\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginFlowTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGateway(): CapturingWhatsappGateway
    {
        $gateway = new CapturingWhatsappGateway;
        $this->app->instance(WhatsappGateway::class, $gateway);

        return $gateway;
    }

    public function test_valid_registered_phone_creates_otp_and_sends_via_gateway(): void
    {
        $gateway = $this->fakeGateway();
        $user = User::factory()->create(['phone' => '6281111111111']);

        // User types the local part only (no leading 0, no 62) — backend normalizes it.
        $response = $this->post('/login', ['phone' => '81111111111']);

        $response->assertRedirect(route('login.otp'));
        $this->assertDatabaseHas('otp_codes', ['user_id' => $user->id]);
        $this->assertSame('6281111111111', $gateway->phone);
        $this->assertNotNull($gateway->code);
    }

    public function test_unregistered_phone_is_rejected(): void
    {
        $response = $this->post('/login', ['phone' => '89999999999']);

        $response->assertSessionHasErrors('phone');
    }

    public function test_correct_code_authenticates_and_redirects_to_dashboard(): void
    {
        $gateway = $this->fakeGateway();
        $user = User::factory()->create(['phone' => '6282222222222']);
        $this->post('/login', ['phone' => '82222222222']);

        $response = $this->post('/login/otp', ['code' => $gateway->code]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_wrong_code_increments_attempts_and_shows_error(): void
    {
        $this->fakeGateway();
        $user = User::factory()->create(['phone' => '6283333333333']);
        $this->post('/login', ['phone' => '83333333333']);

        $response = $this->post('/login/otp', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertSame(1, OtpCode::where('user_id', $user->id)->first()->attempts);
        $this->assertGuest();
    }

    public function test_expired_code_is_rejected(): void
    {
        $gateway = $this->fakeGateway();
        $user = User::factory()->create(['phone' => '6284444444444']);
        $this->post('/login', ['phone' => '84444444444']);

        OtpCode::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);

        $response = $this->post('/login/otp', ['code' => $gateway->code]);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}

class CapturingWhatsappGateway implements WhatsappGateway
{
    public ?string $phone = null;

    public ?string $code = null;

    public function sendOtp(string $phone, string $code): bool
    {
        $this->phone = $phone;
        $this->code = $code;

        return true;
    }
}
