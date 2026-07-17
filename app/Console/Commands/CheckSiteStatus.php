<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\MikrotikService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitoring:check-site-status')]
#[Description('Cek status online/offline tiap Site yang sudah dikonfigurasi (host terisi) lewat MikrotikService::checkStatus()')]
class CheckSiteStatus extends Command
{
    public function handle(MikrotikService $mikrotikService): int
    {
        // Cuma Site yang sudah dikonfigurasi koneksinya (host terisi) yang
        // dicek — Site tanpa host tetap `unknown` selamanya, konsisten
        // status default kolom. Lihat CLAUDE.md "Monitoring".
        $sites = Site::query()->whereNotNull('host')->get();

        $online = 0;

        foreach ($sites as $site) {
            $isOnline = $mikrotikService->checkStatus($site);

            $site->update([
                'status' => $isOnline ? Site::STATUS_ONLINE : Site::STATUS_OFFLINE,
                // last_online_at cuma diperbarui saat online — kalau
                // offline, nilai lama (waktu terakhir benar-benar online)
                // dipertahankan, bukan ditimpa null.
                'last_online_at' => $isOnline ? now() : $site->last_online_at,
            ]);

            if ($isOnline) {
                $online++;
            }
        }

        $this->info("Site dicek: {$sites->count()}, online: {$online}, offline: ".($sites->count() - $online).'.');

        return self::SUCCESS;
    }
}
