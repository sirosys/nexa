<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\Setting;
use App\Services\DismantleService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('dismantle:queue-overdue-suspensions')]
#[Description('Antrekan Service yang sudah suspended lebih lama dari ambang config(dismantle.suspended_months_threshold) untuk dismantle')]
class DismantleQueueOverdueSuspensions extends Command
{
    public function handle(DismantleService $dismantleService): int
    {
        $threshold = now()->subMonths((int) Setting::get('dismantle.suspended_months_threshold', config('dismantle.suspended_months_threshold')));

        $overdue = Service::query()
            ->where('status', Service::STATUS_SUSPENDED)
            ->whereNotNull('suspended_at')
            ->where('suspended_at', '<', $threshold)
            ->get();

        foreach ($overdue as $service) {
            $dismantleService->queue($service);
        }

        $this->info("Diantrekan untuk dismantle: {$overdue->count()} service.");

        return self::SUCCESS;
    }
}
