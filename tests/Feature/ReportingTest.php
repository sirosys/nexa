<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\ServiceActivation;
use App\Models\ServiceDismantle;
use App\Models\ServiceOrder;
use App\Models\ServiceTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportingTest extends TestCase
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

    // ---------- Keuangan/Billing ----------

    public function test_finance_report_aggregates_service_orders_within_invoiced_at_range(): void
    {
        // Lunas dalam range -> masuk revenue & issued.
        ServiceOrder::factory()->create(['invoiced_at' => now(), 'settled_at' => now(), 'grandtotal' => 100000]);
        // Belum lunas dalam range -> masuk unpaid & issued.
        ServiceOrder::factory()->create(['invoiced_at' => now(), 'settled_at' => null, 'canceled_at' => null, 'grandtotal' => 50000]);
        // Dibatalkan dalam range -> masuk canceled & issued.
        ServiceOrder::factory()->create(['invoiced_at' => now(), 'settled_at' => null, 'canceled_at' => now(), 'grandtotal' => 20000]);
        // Lunas DI LUAR range (bulan lalu) -> tidak ikut terhitung sama sekali.
        ServiceOrder::factory()->create(['invoiced_at' => now()->subMonths(2), 'settled_at' => now()->subMonths(2), 'grandtotal' => 999999]);

        $response = $this->actingAs($this->superadmin())->get('/reports/finance');

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return (float) $summary['revenue'] === 100000.0
                && $summary['unpaid_count'] === 1
                && (float) $summary['unpaid_sum'] === 50000.0
                && $summary['canceled_count'] === 1
                && $summary['issued_count'] === 3;
        });
    }

    // ---------- Operasional Lapangan ----------

    public function test_operations_snapshot_counts_are_independent_of_date_filter(): void
    {
        Service::factory()->create(['status' => Service::STATUS_PENDING_INSTALLATION]);
        Service::factory()->create(['status' => Service::STATUS_INSTALLING]);
        Service::factory()->create(['status' => Service::STATUS_PENDING_DISMANTLE]);
        ServiceTicket::create([
            'code' => 'TIK900001',
            'service_id' => Service::factory()->create()->id,
            'category' => ServiceTicket::CATEGORY_LAINNYA,
            'subject' => 'Uji snapshot',
            'description' => 'Tiket terbuka untuk uji snapshot antrean.',
            'status' => ServiceTicket::STATUS_OPEN,
        ]);

        $expected = ['installation_queue' => 2, 'dismantle_queue' => 1, 'open_tickets' => 1];

        $defaultRange = $this->actingAs($this->superadmin())->get('/reports/operations');
        $farFutureRange = $this->actingAs($this->superadmin())->get(
            '/reports/operations?from='.now()->addYear()->format('Y-m-d').'&to='.now()->addYear()->addDay()->format('Y-m-d')
        );

        $defaultRange->assertViewHas('snapshot', fn (array $snapshot) => $snapshot === $expected);
        $farFutureRange->assertViewHas('snapshot', fn (array $snapshot) => $snapshot === $expected);
    }

    public function test_operations_completed_counts_respect_completed_at_and_solved_at_filter(): void
    {
        $inRangeService = Service::factory()->create();
        $inRangeServiceOrder = ServiceOrder::factory()->create(['service_id' => $inRangeService->id]);
        ServiceActivation::create([
            'service_id' => $inRangeService->id,
            'service_order_id' => $inRangeServiceOrder->id,
            'completed_at' => now(),
        ]);

        $outOfRangeService = Service::factory()->create();
        $outOfRangeServiceOrder = ServiceOrder::factory()->create(['service_id' => $outOfRangeService->id]);
        ServiceActivation::create([
            'service_id' => $outOfRangeService->id,
            'service_order_id' => $outOfRangeServiceOrder->id,
            'completed_at' => now()->subMonths(2),
        ]);

        $dismantleService = Service::factory()->create();
        $dismantleServiceOrder = ServiceOrder::factory()->create(['service_id' => $dismantleService->id]);
        $dismantleActivation = ServiceActivation::create([
            'service_id' => $dismantleService->id,
            'service_order_id' => $dismantleServiceOrder->id,
        ]);
        ServiceDismantle::create([
            'service_id' => $dismantleService->id,
            'activation_id' => $dismantleActivation->id,
            'completed_at' => now(),
        ]);

        $ticket = ServiceTicket::create([
            'code' => 'TIK900002',
            'service_id' => Service::factory()->create()->id,
            'category' => ServiceTicket::CATEGORY_LAINNYA,
            'subject' => 'Uji durasi',
            'description' => 'Tiket untuk uji rata-rata durasi penyelesaian.',
            'status' => ServiceTicket::STATUS_RESOLVED,
            'solved_at' => now(),
        ]);
        // created_at bukan kolom fillable ServiceTicket — update langsung
        // lewat query builder supaya durasi solved_at-created_at terkontrol.
        DB::table('service_tickets')->where('id', $ticket->id)->update(['created_at' => now()->subHours(5)]);

        $response = $this->actingAs($this->superadmin())->get('/reports/operations');

        $response->assertOk();
        $response->assertViewHas('summary', function (array $summary) {
            return $summary['installations_completed'] === 1
                && $summary['dismantles_completed'] === 1
                && $summary['tickets_resolved'] === 1
                && (float) $summary['avg_ticket_resolution_hours'] === 5.0;
        });
    }

    // ---------- Pelanggan & Layanan ----------

    public function test_customers_status_distribution_shows_all_statuses_and_ignores_date_filter(): void
    {
        Service::factory()->count(2)->create(['status' => Service::STATUS_ACTIVE]);

        $response = $this->actingAs($this->superadmin())->get(
            '/reports/customers?from='.now()->addYear()->format('Y-m-d').'&to='.now()->addYear()->addDay()->format('Y-m-d')
        );

        $response->assertOk();
        $response->assertViewHas('status_distribution', function (array $rows) {
            $statuses = array_column($rows, 'status');

            return $statuses === Service::STATUSES
                && $rows[array_search(Service::STATUS_ACTIVE, $statuses, true)]['count'] === 2
                && $rows[array_search(Service::STATUS_CANCELED, $statuses, true)]['count'] === 0;
        });
    }

    public function test_customers_new_services_respect_created_at_filter(): void
    {
        $inRange = Service::factory()->create();
        $outOfRange = Service::factory()->create();
        DB::table('services')->where('id', $outOfRange->id)->update(['created_at' => now()->subMonths(2)]);

        $response = $this->actingAs($this->superadmin())->get('/reports/customers');

        $response->assertOk();
        $response->assertViewHas('summary', fn (array $summary) => $summary['new_services'] === 1);
        $response->assertViewHas('services', fn ($services) => $services->total() === 1 && $services->first()->id === $inRange->id);
    }

    // ---------- Akses ----------

    /**
     * `finance` dikecualikan — sejak diperluas jadi "Admin/NOC" (2026-07-23)
     * role itu punya `reports.view`.
     */
    public function test_non_superadmin_roles_get_403_on_all_report_routes(): void
    {
        foreach (['technician', 'customer'] as $role) {
            $user = $this->withRole($role);

            foreach (['/reports/finance', '/reports/operations', '/reports/customers'] as $route) {
                $this->actingAs($user)->get($route)->assertForbidden();
            }
        }
    }

    public function test_finance_can_access_all_report_routes(): void
    {
        $finance = $this->withRole('finance');

        foreach (['/reports/finance', '/reports/operations', '/reports/customers'] as $route) {
            $this->actingAs($finance)->get($route)->assertOk();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        foreach (['/reports/finance', '/reports/operations', '/reports/customers'] as $route) {
            $this->get($route)->assertRedirect('/login');
        }
    }

    public function test_invalid_date_filter_does_not_return_500(): void
    {
        $response = $this->actingAs($this->superadmin())->get('/reports/finance?from=bukan-tanggal');

        $response->assertStatus(302);
    }
}
