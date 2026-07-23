<?php

namespace App\Console\Commands;

use App\Models\ServiceOrder;
use App\Models\Setting;
use App\Services\RenewalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

#[Signature('renewal:send-reminders')]
#[Description('Kirim reminder H-3/H-1 untuk tagihan perpanjangan yang masih belum dibayar')]
class RenewalSendReminders extends Command
{
    public function handle(RenewalService $renewalService): int
    {
        $h3 = $this->due('h3', 'renewal_reminder_h3_sent_at');

        foreach ($h3 as $serviceOrder) {
            $renewalService->sendReminder($serviceOrder, 3);
        }

        $h1 = $this->due('h1', 'renewal_reminder_h1_sent_at');

        foreach ($h1 as $serviceOrder) {
            $renewalService->sendReminder($serviceOrder, 1);
        }

        $this->info("Reminder H-3 terkirim: {$h3->count()}. Reminder H-1 terkirim: {$h1->count()}.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, ServiceOrder>
     */
    private function due(string $configKey, string $sentAtColumn): Collection
    {
        return ServiceOrder::query()
            ->where('is_renewal', true)
            ->whereNull('settled_at')
            ->whereNull('canceled_at')
            ->whereNull($sentAtColumn)
            ->whereHas('service', fn ($query) => $query->where(
                'expired_at',
                '<=',
                now()->addDays((int) Setting::get("renewal.remind_days_before.{$configKey}", config("renewal.remind_days_before.{$configKey}"))),
            ))
            ->with('service.user', 'receipt')
            ->get();
    }
}
