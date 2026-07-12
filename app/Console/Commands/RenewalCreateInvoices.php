<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\RenewalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('renewal:create-invoices')]
#[Description('Buat tagihan perpanjangan (Sale) otomatis untuk Service yang mendekati masa expired (H-5)')]
class RenewalCreateInvoices extends Command
{
    public function handle(RenewalService $renewalService): int
    {
        $due = Service::query()
            ->where('status', Service::STATUS_ACTIVE)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now()->addDays((int) config('renewal.remind_days_before.invoice')))
            ->get();

        $created = 0;

        foreach ($due as $service) {
            if ($renewalService->createInvoiceForDueService($service)) {
                $created++;
            }
        }

        $this->info("Tagihan perpanjangan dibuat: {$created} dari {$due->count()} service yang diperiksa.");

        return self::SUCCESS;
    }
}
