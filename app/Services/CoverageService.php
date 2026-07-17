<?php

namespace App\Services;

use App\Models\Coverage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CoverageService
{
    public function create(array $data): Coverage
    {
        return DB::transaction(function () use ($data) {
            $coverage = Coverage::create([
                'site_id' => $data['site_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $coverage->update([
                'code' => 'COV'.str_pad((string) $coverage->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $coverage;
        });
    }

    public function update(Coverage $coverage, array $data): Coverage
    {
        $coverage->update([
            'site_id' => $data['site_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        return $coverage;
    }

    public function delete(Coverage $coverage): void
    {
        $coverage->delete();
    }
}
