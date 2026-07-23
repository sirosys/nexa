<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\ServiceOrder;
use App\Services\AuditLogService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('billing:cancel-expired-invoices')]
#[Description('Batalkan tagihan pendaftaran (Order Layanan) yang sudah lewat jatuh tempo dan belum dibayar')]
class CancelExpiredInvoices extends Command
{
    /**
     * Satu-satunya jalur pembatalan invoice di sistem (lihat CLAUDE.md
     * "Billing / Invoice (Xendit)") — webhook Xendit sengaja tidak ikut
     * membatalkan Order Layanan/Service supaya tidak ada dua sumber
     * kebenaran soal kapan sesuatu dianggap batal.
     */
    public function handle(AuditLogService $auditLogService): int
    {
        $expired = ServiceOrder::query()
            ->whereNotNull('invoiced_at')
            ->whereNull('settled_at')
            ->whereNull('canceled_at')
            ->where('expired_at', '<', now())
            ->with('service', 'receipt')
            ->get();

        foreach ($expired as $serviceOrder) {
            DB::transaction(function () use ($serviceOrder) {
                $serviceOrder->update(['canceled_at' => now()]);

                $serviceOrder->service?->update(['status' => Service::STATUS_CANCELED]);

                $serviceOrder->receipt?->update(['status' => 'EXPIRED']);
            });

            $auditLogService->record('service_order.canceled', $serviceOrder, "Tagihan {$serviceOrder->code} dibatalkan otomatis (lewat jatuh tempo).");
        }

        $this->info("Dibatalkan: {$expired->count()} tagihan.");

        return self::SUCCESS;
    }
}
