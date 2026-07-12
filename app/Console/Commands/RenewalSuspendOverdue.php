<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Services\RenewalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('renewal:suspend-overdue')]
#[Description('Suspend Service yang tagihan perpanjangannya belum dibayar setelah lewat expired_at')]
class RenewalSuspendOverdue extends Command
{
    /**
     * Sengaja tidak cek keberadaan Sale renewal — kalau Service active dan
     * expired_at sudah lewat, suspend tetap jalan (defensif: kalau
     * renewal:create-invoices gagal/tidak sempat jalan untuk Service ini,
     * layanan tetap tidak boleh diam-diam terus aktif tanpa tagihan).
     */
    public function handle(RenewalService $renewalService): int
    {
        $overdue = Service::query()
            ->where('status', Service::STATUS_ACTIVE)
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->get();

        foreach ($overdue as $service) {
            $renewalService->suspend($service);
        }

        $this->info("Disuspend: {$overdue->count()} service.");

        return self::SUCCESS;
    }
}
