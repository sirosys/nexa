<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SiteService
{
    public function create(array $data): Site
    {
        return DB::transaction(function () use ($data) {
            $site = Site::create([
                'name' => $data['name'],
                'subdistrict_id' => $data['subdistrict_id'],
                'serial' => $data['serial'] ?? null,
                'model' => $data['model'] ?? null,
                'location' => $data['location'] ?? null,
                'token' => $data['token'] ?? null,
                'host' => $data['host'] ?? null,
                'api_port' => $data['api_port'] ?? null,
                'api_username' => $data['api_username'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $site->update([
                'code' => 'SIT'.str_pad((string) $site->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $site;
        });
    }

    public function update(Site $site, array $data): Site
    {
        $site->update([
            'name' => $data['name'],
            'subdistrict_id' => $data['subdistrict_id'],
            'serial' => $data['serial'] ?? null,
            'model' => $data['model'] ?? null,
            'location' => $data['location'] ?? null,
            'token' => $data['token'] ?? $site->token,
            'host' => $data['host'] ?? null,
            'api_port' => $data['api_port'] ?? null,
            'api_username' => $data['api_username'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        return $site;
    }

    public function delete(Site $site): void
    {
        $site->delete();
    }
}
