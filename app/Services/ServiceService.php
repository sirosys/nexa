<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ServiceService
{
    public function create(array $data): Service
    {
        return DB::transaction(function () use ($data) {
            $service = Service::create([
                'pin' => $this->generatePin(),
                'user_id' => $data['user_id'],
                'address' => $data['address'],
                'residential_name' => $data['residential_name'] ?? null,
                'subdistrict_id' => $data['subdistrict_id'],
                'rw' => $data['rw'] ?? null,
                'rt' => $data['rt'] ?? null,
                'coverage_id' => $data['coverage_id'],
                'package_id' => $data['package_id'],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $service->update([
                'code' => 'SRV'.str_pad((string) $service->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $service;
        });
    }

    public function update(Service $service, array $data): Service
    {
        $service->update([
            'pin' => $data['pin'] ?? $service->pin,
            'user_id' => $data['user_id'],
            'address' => $data['address'],
            'residential_name' => $data['residential_name'] ?? null,
            'subdistrict_id' => $data['subdistrict_id'],
            'rw' => $data['rw'] ?? null,
            'rt' => $data['rt'] ?? null,
            'coverage_id' => $data['coverage_id'],
            'package_id' => $data['package_id'],
            'updated_by' => Auth::id(),
        ]);

        return $service;
    }

    public function delete(Service $service): void
    {
        $service->delete();
    }

    private function generatePin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
