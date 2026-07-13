<?php

namespace App\Console\Commands;

use App\Models\Pop;
use App\Services\MikrotikService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitoring:check-pop-status')]
#[Description('Cek status online/offline tiap PoP yang sudah dikonfigurasi (host terisi) lewat MikrotikService::checkStatus()')]
class CheckPopStatus extends Command
{
    public function handle(MikrotikService $mikrotikService): int
    {
        // Cuma PoP yang sudah dikonfigurasi koneksinya (host terisi) yang
        // dicek — PoP tanpa host tetap `unknown` selamanya, konsisten
        // status default kolom. Lihat CLAUDE.md "Monitoring".
        $pops = Pop::query()->whereNotNull('host')->get();

        $online = 0;

        foreach ($pops as $pop) {
            $isOnline = $mikrotikService->checkStatus($pop);

            $pop->update([
                'status' => $isOnline ? Pop::STATUS_ONLINE : Pop::STATUS_OFFLINE,
                // last_online_at cuma diperbarui saat online — kalau
                // offline, nilai lama (waktu terakhir benar-benar online)
                // dipertahankan, bukan ditimpa null.
                'last_online_at' => $isOnline ? now() : $pop->last_online_at,
            ]);

            if ($isOnline) {
                $online++;
            }
        }

        $this->info("PoP dicek: {$pops->count()}, online: {$online}, offline: ".($pops->count() - $online).'.');

        return self::SUCCESS;
    }
}
