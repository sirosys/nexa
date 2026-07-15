<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PlanService
{
    public function create(array $data): Plan
    {
        return DB::transaction(function () use ($data) {
            $plan = Plan::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $plan->update([
                'code' => 'PLN'.str_pad((string) $plan->id, 6, '0', STR_PAD_LEFT),
            ]);

            return $plan;
        });
    }

    public function update(Plan $plan, array $data): Plan
    {
        $plan->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'updated_by' => Auth::id(),
        ]);

        return $plan;
    }

    public function delete(Plan $plan): void
    {
        $plan->delete();
    }
}
