<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Setting;
use App\Models\User;
use App\Services\ReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        return $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Form/validasi di-key oleh id (bukan `key` string) — lihat
     * SettingUpdateRequest kenapa. Helper ini menerjemahkan payload yang
     * enak dibaca (keyed by `key`) jadi payload sungguhan (keyed by id).
     *
     * @param  array<string, int|string>  $valuesByKey
     * @return array<string, array<int, int|string>>
     */
    private function payload(array $valuesByKey): array
    {
        $ids = Setting::query()->pluck('id', 'key');

        $settings = [];
        foreach ($valuesByKey as $key => $value) {
            $settings[$ids[$key]] = $value;
        }

        return ['settings' => $settings];
    }

    public function test_settings_table_is_seeded_with_expected_keys_and_defaults(): void
    {
        $this->assertSame('3', Setting::where('key', 'billing.invoice_ttl_days')->value('value'));
        $this->assertSame('5', Setting::where('key', 'renewal.remind_days_before.invoice')->value('value'));
        $this->assertSame('3', Setting::where('key', 'renewal.remind_days_before.h3')->value('value'));
        $this->assertSame('1', Setting::where('key', 'renewal.remind_days_before.h1')->value('value'));
        $this->assertSame('2', Setting::where('key', 'dismantle.suspended_months_threshold')->value('value'));
    }

    public function test_superadmin_can_view_settings_page(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/settings');

        $response->assertOk();
        $response->assertSee('Masa Berlaku Tagihan Pendaftaran', false);
        $response->assertSee('value="3"', false);
    }

    public function test_superadmin_can_update_settings(): void
    {
        $admin = $this->superadmin();

        $response = $this->actingAs($admin)->put('/settings', $this->payload([
            'billing.invoice_ttl_days' => 7,
            'renewal.remind_days_before.invoice' => 6,
            'renewal.remind_days_before.h3' => 4,
            'renewal.remind_days_before.h1' => 2,
            'dismantle.suspended_months_threshold' => 3,
        ]));

        $response->assertRedirect('/settings');
        $response->assertSessionHas('status');

        $setting = Setting::where('key', 'billing.invoice_ttl_days')->first();
        $this->assertSame('7', $setting->value);
        $this->assertSame($admin->id, $setting->updated_by);
        $this->assertSame('6', Setting::where('key', 'renewal.remind_days_before.invoice')->value('value'));
        $this->assertSame('4', Setting::where('key', 'renewal.remind_days_before.h3')->value('value'));
        $this->assertSame('2', Setting::where('key', 'renewal.remind_days_before.h1')->value('value'));
        $this->assertSame('3', Setting::where('key', 'dismantle.suspended_months_threshold')->value('value'));
    }

    public function test_update_rejects_non_integer_and_below_minimum_values(): void
    {
        $ids = Setting::query()->pluck('id', 'key');

        $response = $this->actingAs($this->superadmin())->put('/settings', $this->payload([
            'billing.invoice_ttl_days' => 0,
            'renewal.remind_days_before.invoice' => 'lima',
            'renewal.remind_days_before.h3' => 3,
            'renewal.remind_days_before.h1' => 1,
            'dismantle.suspended_months_threshold' => 2,
        ]));

        $response->assertSessionHasErrors([
            'settings.'.$ids['billing.invoice_ttl_days'],
            'settings.'.$ids['renewal.remind_days_before.invoice'],
        ]);
        $this->assertSame('3', Setting::where('key', 'billing.invoice_ttl_days')->value('value'));
    }

    public function test_non_superadmin_roles_are_forbidden(): void
    {
        // Payload harus lolos validasi SettingUpdateRequest dulu (semua key
        // wajib) supaya authorize() di controller yang benar-benar diuji,
        // bukan tertutup oleh error validasi lebih dulu (pola sama
        // InstallationManagementTest untuk action non-resource serupa).
        $validPayload = $this->payload([
            'billing.invoice_ttl_days' => 3,
            'renewal.remind_days_before.invoice' => 5,
            'renewal.remind_days_before.h3' => 3,
            'renewal.remind_days_before.h1' => 1,
            'dismantle.suspended_months_threshold' => 2,
        ]);

        foreach (['technician', 'finance', 'sales', 'customer'] as $role) {
            $user = $this->withRole($role);

            $this->actingAs($user)->get('/settings')->assertForbidden();
            $this->actingAs($user)->put('/settings', $validPayload)->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/settings')->assertRedirect('/login');
    }

    public function test_setting_get_falls_back_to_default_when_row_missing(): void
    {
        Setting::where('key', 'billing.invoice_ttl_days')->delete();

        $this->assertSame(99, Setting::get('billing.invoice_ttl_days', 99));
    }

    public function test_setting_get_falls_back_to_default_when_value_is_null(): void
    {
        Setting::where('key', 'billing.invoice_ttl_days')->update(['value' => null]);

        $this->assertSame(99, Setting::get('billing.invoice_ttl_days', 99));
    }

    /**
     * Bukti wiring end-to-end: mengubah setting lewat DB benar-benar
     * mengubah perilaku ReceiptService, bukan cuma mengubah baris di tabel
     * settings tanpa efek apa pun.
     */
    public function test_updated_invoice_ttl_setting_is_respected_by_receipt_service(): void
    {
        Setting::where('key', 'billing.invoice_ttl_days')->update(['value' => '10']);

        $sale = Sale::factory()->create(['grandtotal' => 100000]);

        app(ReceiptService::class)->createForSale($sale);

        $sale->refresh();
        $this->assertEqualsWithDelta(now()->addDays(10)->timestamp, $sale->expired_at->timestamp, 5);
    }
}
