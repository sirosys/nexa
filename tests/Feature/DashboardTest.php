<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\Service;
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
        // seluruh Service/Sale di bawah supaya ServiceFactory/SaleFactory
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
        Sale::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => null, 'canceled_at' => null]);
        Sale::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => null, 'canceled_at' => null]);
        // Sudah lunas bulan ini — ikut dihitung sebagai pendapatan.
        Sale::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => now(), 'grandtotal' => 150000]);
        Sale::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => now(), 'grandtotal' => 250000]);
        // Lunas bulan lalu — TIDAK ikut "pendapatan bulan ini".
        Sale::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now()->subMonth(), 'settled_at' => now()->subMonth(), 'grandtotal' => 999999]);
        // Dibatalkan — bukan tagihan belum lunas maupun pendapatan.
        Sale::factory()->create(['service_id' => $billingService->id, 'invoiced_at' => now(), 'settled_at' => null, 'canceled_at' => now()]);

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
        Sale::factory()->create(['settled_at' => now(), 'grandtotal' => 100000]);

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
        foreach (['superadmin', 'technician', 'finance', 'sales'] as $role) {
            $this->actingAs($this->withRole($role))->get('/dashboard')->assertOk();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
