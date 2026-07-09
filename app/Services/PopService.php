<?php

namespace App\Services;

use App\Models\Pop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PopService
{
    public function create(array $data): Pop
    {
        return DB::transaction(function () use ($data) {
            $pop = Pop::create([
                'name' => $data['name'],
                'subdistrict_id' => $data['subdistrict_id'],
                'serial' => $data['serial'] ?? null,
                'model' => $data['model'] ?? null,
                'location' => $data['location'] ?? null,
                'token' => $data['token'] ?? null,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $pop->update([
                'code' => 'POP'.str_pad((string) $pop->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $pop;
        });
    }

    public function update(Pop $pop, array $data): Pop
    {
        $pop->update([
            'name' => $data['name'],
            'subdistrict_id' => $data['subdistrict_id'],
            'serial' => $data['serial'] ?? null,
            'model' => $data['model'] ?? null,
            'location' => $data['location'] ?? null,
            'token' => $data['token'] ?? $pop->token,
            'updated_by' => Auth::id(),
        ]);

        return $pop;
    }

    public function delete(Pop $pop): void
    {
        $pop->delete();
    }
}
