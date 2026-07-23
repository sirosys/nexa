<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\GeneratesValidNik;
use Tests\TestCase;

class CompleteOwnKycTest extends TestCase
{
    use GeneratesValidNik, RefreshDatabase;

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        return $user;
    }

    public function test_customer_can_complete_own_kyc(): void
    {
        Storage::fake('local');
        $customer = $this->customer();
        $nik = $this->validNik();

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/me/complete-kyc', [
            'nik' => $nik,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.nik', $nik);
        $response->assertJsonPath('data.has_ktp_photo', true);

        $customer->refresh();
        $this->assertSame($nik, $customer->userDetails->nik);
        $this->assertNotNull($customer->userDetails->ktp_photo);
        Storage::disk('local')->assertExists($customer->userDetails->ktp_photo);
    }

    public function test_nik_is_required(): void
    {
        Storage::fake('local');
        Sanctum::actingAs($this->customer());

        $response = $this->postJson('/api/v1/me/complete-kyc', [
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['nik']);
    }

    public function test_ktp_photo_is_required(): void
    {
        Sanctum::actingAs($this->customer());

        $response = $this->postJson('/api/v1/me/complete-kyc', [
            'nik' => $this->validNik(),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['ktp_photo']);
    }

    public function test_ktp_photo_must_be_an_image(): void
    {
        Sanctum::actingAs($this->customer());

        $response = $this->postJson('/api/v1/me/complete-kyc', [
            'nik' => $this->validNik(),
            'ktp_photo' => UploadedFile::fake()->create('ktp.pdf', 100),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['ktp_photo']);
    }

    public function test_duplicate_nik_is_rejected(): void
    {
        Storage::fake('local');
        $nik = $this->validNik();

        $owner = $this->customer();
        $owner->userDetails()->create(['nik' => $nik]);

        Sanctum::actingAs($this->customer());

        $response = $this->postJson('/api/v1/me/complete-kyc', [
            'nik' => $nik,
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['nik']);
    }

    public function test_cannot_recomplete_kyc_once_nik_is_set(): void
    {
        Storage::fake('local');
        $customer = $this->customer();
        $customer->userDetails()->create(['nik' => $this->validNik()]);

        Sanctum::actingAs($customer);

        $response = $this->postJson('/api/v1/me/complete-kyc', [
            'nik' => $this->validNik(),
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['nik']);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/v1/me/complete-kyc', [
            'nik' => $this->validNik(),
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ])->assertStatus(401);
    }

    public function test_endpoint_is_rate_limited(): void
    {
        Storage::fake('local');
        $customer = $this->customer();
        Sanctum::actingAs($customer);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/me/complete-kyc', [
                'nik' => $this->validNik(),
                'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
            ]);
        }

        $response = $this->postJson('/api/v1/me/complete-kyc', [
            'nik' => $this->validNik(),
            'ktp_photo' => UploadedFile::fake()->image('ktp.jpg'),
        ]);

        $response->assertStatus(429);
    }
}
