<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\Setting;
use App\Services\RenewalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;

#[Signature('renewal:create-invoices')]
#[Description('Buat tagihan perpanjangan (Sale) otomatis untuk Service yang mendekati masa expired (H-5)')]
class RenewalCreateInvoices extends Command
{
    public function handle(RenewalService $renewalService): int
    {
        $due = Service::query()
            ->where('status', Service::STATUS_ACTIVE)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now()->addDays((int) Setting::get('renewal.remind_days_before.invoice', config('renewal.remind_days_before.invoice'))))
            ->get();

        $created = 0;

        foreach ($due as $service) {
            try {
                if ($renewalService->createInvoiceForDueService($service)) {
                    $created++;
                }
            } catch (RuntimeException $e) {
                // Paket Service ini belum lengkap (mis. plan_id
                // kosong) — jangan sampai satu Service yang belum
                // dikonfigurasi dengan benar menghentikan renewal Service
                // lain di batch yang sama.
                Log::warning('Gagal membuat tagihan perpanjangan.', [
                    'service_id' => $service->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Tagihan perpanjangan dibuat: {$created} dari {$due->count()} service yang diperiksa.");

        return self::SUCCESS;
    }
}
