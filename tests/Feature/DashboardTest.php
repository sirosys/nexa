<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    public function test_dashboard_shows_real_operational_stats(): void
    {
        // 3 customer yang mau dihitung, plus 1 "pemilik" dipakai ulang untuk
        // seluruh Service/ServiceOrder di bawah supaya ServiceFactory/ServiceOrderFactory
        // tidak diam-diam membuat customer baru lewat relasi default
        // (user_id di-override -> closure User::factory() bawaan tidak
        // pernah dievaluasi), jadi total customer tetap presisi terkontrol.
        User::factory()->count(3)->create()->each(fn (User $user) => $user->assignRole('customer'));
        $owner = User::factory()->create();
        $owner->assignRole('customer');

        Service::factory()->count(2)->create(['status' => Service::STATUS_ACTIVE, 'user_id' => $owner->id]);
        Service::factory()->create(['status' => Service::STATUS_SUSPENDED, 'user_id' => $owner->id]);
        Service::factory()->create(['status' => Service::STATUS_PENDING_INSTALLATION, 'user_id' => $owner->id]);
        Service::factory()->create(['status' => Service::STATUS_INSTALLING, 'user_id' => $owner->id]);
        Service::factory()->create(['status' => Service::STATUS_PENDING_DISMANTLE, 'user_id' => $owner->id]);
        Service::factory()->create(['status' => Service::STATUS_DISMANTLING, 'user_id' => $owner->id]);

        $billingService = Service::factory()->create(['user_id' => $owner->id]);

        // Tagihan belum lunas: sudah di-invoice, belum settled/canceled.
        ServiceOrder::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => null, 'canceled_at' => null]);
        ServiceOrder::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => null, 'canceled_at' => null]);
        // Sudah lunas bulan ini — ikut dihitung sebagai pendapatan.
        ServiceOrder::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => now(), 'grandtotal' => 150000]);
        ServiceOrder::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => now(), 'grandtotal' => 250000]);
        // Lunas bulan lalu — TIDAK ikut "pendapatan bulan ini".
        ServiceOrder::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now()->subMonth(), 'settled_at' => now()->subMonth(), 'grandtotal' => 999999]);
        // Dibatalkan — bukan tagihan belum lunas maupun pendapatan.
        ServiceOrder::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => null, 'canceled_at' => now()]);

        $response = $this->actingAs($this->withRole('superadmin'))->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('stats', function (array $stats) {
            return $stats['registered_customers'] === 4
                && $stats['active_services'] === 2
                && $stats['unpaid_invoices'] === 2
                && (float) $stats['revenue_this_month'] === 400000.0
                && $stats['installation_queue'] === 2
                && $stats['dismantle_queue'] === 2;
        });
    }

    public function test_service_status_distribution_follows_lifecycle_order_with_zero_for_missing_status(): void
    {
        Service::factory()->count(2)->create(['status' => Service::STATUS_ACTIVE]);

        $response = $this->actingAs($this->withRole('superadmin'))->get('/dashboard');

        $response->assertViewHas('statusDistribution', function (array $rows) {
            $statuses = array_column($rows, 'status');

            return $statuses === Service::STATUSES
                && $rows[array_search(Service::STATUS_ACTIVE, $statuses, true)]['count'] === 2
                && $rows[array_search(Service::STATUS_CANCELED, $statuses, true)]['count'] === 0;
        });
    }

    public function test_monthly_revenue_covers_six_months_ending_this_month(): void
    {
        ServiceOrder::factory()->create(['settled_at' => now(), 'grandtotal' => 100000]);

        $response = $this->actingAs($this->withRole('superadmin'))->get('/dashboard');

        $response->assertViewHas('monthlyRevenue', function (array $months) {
            return count($months) === 6
                && (float) $months[5]['total'] === 100000.0
                && $months[5]['label'] === now()->translatedFormat('M Y');
        });
    }

    public function test_dashboard_recent_services_lists_latest_first(): void
    {
        $older = Service::factory()->create();
        $newer = Service::factory()->create();

        $response = $this->actingAs($this->withRole('superadmin'))->get('/dashboard');

        $response->assertViewHas('recentServices', function ($services) use ($older, $newer) {
            return $services->first()->id === $newer->id
                && $services->last()->id === $older->id;
        });
    }

    public function test_dashboard_is_accessible_to_every_authenticated_role(): void
    {
        foreach (['superadmin', 'technician', 'finance'] as $role) {
            $this->actingAs($this->withRole($role))->get('/dashboard')->assertOk();
        }
    }

    /**
     * Role 'sales' dihapus total 2026-07-17 — technician sekarang ikut
     * dapat services.view/service_orders.view (permission registrasi pelanggan
     * dibagikan ke semua staff, lihat CLAUDE.md "Authorization / Role &
     * Permission"), jadi dashboard-nya ikut menampilkan data layanan/tagihan
     * di samping antrean instalasi/dismantle — bukan lagi cuma dua stat itu.
     */
    public function test_technician_sees_installation_dismantle_and_service_order_stats(): void
    {
        Service::factory()->create(['status' => Service::STATUS_ACTIVE]);
        ServiceOrder::factory()->create(['settled_at' => now(), 'grandtotal' => 100000]);

        $response = $this->actingAs($this->withRole('technician'))->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('stats', fn (array $stats) => array_keys($stats) === [
            'active_services', 'unpaid_invoices', 'revenue_this_month', 'installation_queue', 'dismantle_queue',
        ]);
        $response->assertViewHas('statusDistribution', fn ($value) => $value !== null);
        $response->assertViewHas('monthlyRevenue', fn ($value) => $value !== null);
        $response->assertViewHas('recentServices', fn ($value) => $value !== null);
    }

    /**
     * `finance` diperluas jadi "Admin/NOC" (2026-07-23) — sekarang punya
     * `users.view`/`installations.view`/`dismantles.view` juga, jadi ikut
     * melihat seluruh kartu statistik, bukan cuma yang finansial/layanan
     * seperti sebelum perluasan itu.
     */
    public function test_finance_sees_all_dashboard_stat_cards(): void
    {
        Service::factory()->create(['status' => Service::STATUS_ACTIVE]);

        $response = $this->actingAs($this->withRole('finance'))->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('stats', fn (array $stats) => array_keys($stats) === [
            'registered_customers', 'active_services', 'unpaid_invoices', 'revenue_this_month', 'installation_queue', 'dismantle_queue',
        ]);
        $response->assertViewHas('statusDistribution', fn ($value) => $value !== null);
        $response->assertViewHas('monthlyRevenue', fn ($value) => $value !== null);
        $response->assertViewHas('recentServices', fn ($value) => $value !== null);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
