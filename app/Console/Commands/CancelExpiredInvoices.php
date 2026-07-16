<?php

namespace App\Console\Commands;

use App\Models\Sale;
use App\Models\Service;
use App\Services\AuditLogService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('billing:cancel-expired-invoices')]
#[Description('Batalkan tagihan pendaftaran (Sale) yang sudah lewat jatuh tempo dan belum dibayar')]
class CancelExpiredInvoices extends Command
{
    /**
     * Satu-satunya jalur pembatalan invoice di sistem (lihat CLAUDE.md
     * "Billing / Invoice (Xendit)") — webhook Xendit sengaja tidak ikut
     * membatalkan Sale/Service supaya tidak ada dua sumber kebenaran soal
     * kapan sesuatu dianggap batal.
     */
    public function handle(AuditLogService $auditLogService): int
    {
        $expired = Sale::query()
            ->whereNotNull('invoiced_at')
            ->whereNull('settled_at')
            ->whereNull('canceled_at')
            ->where('expired_at', '<', now())
            ->with('service', 'receipt')
            ->get();

        foreach ($expired as $sale) {
            DB::transaction(function () use ($sale) {
                $sale->update(['canceled_at' => now()]);

                $sale->service?->update(['status' => Service::STATUS_CANCELED]);

                $sale->receipt?->update(['status' => 'EXPIRED']);
            });

            $auditLogService->record('sale.canceled', $sale, "Tagihan {$sale->code} dibatalkan otomatis (lewat jatuh tempo).");
        }

        $this->info("Dibatalkan: {$expired->count()} tagihan.");

        return self::SUCCESS;
    }
}
